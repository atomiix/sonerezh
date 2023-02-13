<?php

declare(strict_types=1);

namespace App\Controller;

use Brick\VarExporter\VarExporter;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;
use Cake\Event\EventInterface;
use Cake\Filesystem\File;
use Cake\Form\Form;
use Cake\Utility\Security;
use Exception;
use PDO;
use PDOException;

/**
 * Class InstallationsController
 * Sonerezh installation controller.
 */
class InstallersController extends AppController
{
    private const SCHEMA_FILE = CONFIG.'schema/schema.php';
    private const CONFIG_FILE = CONFIG.'app_config.php';

    public function beforeFilter(EventInterface $event): void
    {
        $this->components()->unload('FormProtection');
        $this->components()->unload('Authentication');
        parent::beforeFilter($event);
    }

    public function isAuthorized($user): bool
    {
        return true;
    }

    /**
     * This function deploys Sonerezh
     * It connects to MySQL / MariaDB with the provided credentials, tries to create the database and populates it.
     * The first users is also created here, with the administrator role, and the default settings are applied.
     */
    public function index()
    {
        $requirements = [];
        $missing_requirements = false;

        $gd = extension_loaded('gd');

        if ($gd) {
            $requirements['gd'] = ['label' => 'success', 'message' => __('PHP GD is available and loaded.')];
        } else {
            $requirements['gd'] = ['label' => 'danger', 'message' => __('PHP GD is missing.')];
            $missing_requirements = true;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $libavtools = shell_exec('where avconv') || shell_exec('where ffmpeg');  // WIN
        } else {
            $libavtools = shell_exec('which avconv') || shell_exec('which ffmpeg');  //NO WIN
        }

        if ($libavtools) {
            $requirements['libavtools'] = ['label' => 'success', 'message' => __('libav-tools (avconv) or ffmpeg is installed!')];
        } else {
            $requirements['libavtools'] = ['label' => 'warning', 'message' => __('libav-tools (avconv) or ffmpeg is missing. Sonerezh will not be able to convert your tracks.')];
        }

        $pdo_drivers = PDO::getAvailableDrivers();
        $available_drivers = []; // Used to load options on the view
        $drivers = ['mysql', 'pgsql', 'sqlite'];

        if (empty($pdo_drivers)) {
            $requirements['pdo_drivers'] = ['label' => 'danger', 'message' => __('At least one PDO driver must be installed to run Sonerezh (mysql, pgsql or sqlite)')];
            $missing_requirements = true;
        } else {
            foreach ($drivers as $driver) {
                if (in_array($driver, $pdo_drivers, true)) {
                    $requirements[$driver] = ['label' => 'success', 'message' => __('The {0} driver is installed.', $driver)];

                    switch ($driver) {
                        case 'mysql':
                            $available_drivers['mysql'] = 'MySQL';
                            break;
                        case 'pgsql':
                            $available_drivers['postgresql'] = 'PostgreSQL';
                            break;
                        case 'sqlite':
                            $available_drivers['sqlite'] = 'SQLite';
                            break;
                    }
                } else {
                    $requirements[$driver] = ['label' => 'warning', 'message' => __('The {0} driver is required if you want to use Sonerezh with {1}', $driver, $driver)];
                }
            }
        }

        $is_config_writable = is_writable(CONFIG);

        if ($is_config_writable) {
            $requirements['conf'] = ['label' => 'success', 'message' => __('{0} is writable', CONFIG)];
        } else {
            $requirements['conf'] = ['label' => 'danger', 'message' => __('{0} is not writable', CONFIG)];
            $missing_requirements = true;
        }

        $form = new Form();
        $this->viewBuilder()->setLayout('installer');
        $this->set(compact('requirements', 'missing_requirements', 'available_drivers', 'form'));

        if ($this->request->is('post')) {
            $datasources = ['mysql', 'postgresql', 'sqlite'];

            if (in_array($this->request->getData('DB.datasource'), $datasources, true)) {
                $data = $this->request->getData('DB');
                $dsn = sprintf(
                    '%s://%s:%s@%s/%s?encoding=utf8&timezone=UTC&cacheMetadata=true&quoteIdentifiers=false&persistent=false',
                    $data['datasource'],
                    $data['login'],
                    $data['password'],
                    $data['host'],
                    $data['datasource'] === 'sqlite' ? (new File($data['database']))->path : $data['database']
                );
            } else {
                $this->Flash->error(__('Wrong datasource.'));
                return;
            }

            if ($this->request->getData('DB.datasource') === 'sqlite') {
                $sqlite_file = new File($this->request->getData('DB.database'));

                // Create SQlite database file if it does not exist
                if (!file_exists($sqlite_file->path)) {
                    if (!$sqlite_file->create()) {
                        $this->Flash->error(__('Unable to create the SQlite database file.'));
                        return;
                    }
                } elseif (!is_file($sqlite_file->path)) {
                    $this->Flash->error(__('This is not a regular file: {0}', $sqlite_file->path));
                    return;
                }
            }

            // Write config/app_config.php
            $db_config_file = new File(self::CONFIG_FILE);

            if ($db_config_file->create()) {
                $data = [
                    'Security' => ['salt' => hash('sha256', Security::randomBytes(64))],
                    'Datasources'=> ['default' => ['url' => $dsn]],
                ];
                $db_config_data = "<?php\n\n".VarExporter::export($data, VarExporter::ADD_RETURN)."\n";
                $db_config_file->write($db_config_data);
            } else {
                $this->Flash->error(__('Unable to write {0}', self::CONFIG_FILE));
                return;
            }

            // Check database connection
            try {
                ConnectionManager::setConfig('default', $data['Datasources']['default']);
                $connection = ConnectionManager::get('default');
                $connection->getDriver()->connect();
            } catch (Exception $e) {
                $this->Flash->error(__('Could not connect to database'));
                return $this->__cleanAndReturn();
            }

            // Populate Sonerezh database
            // Export schema
            /** @var TableSchema[] $tables */
            $tables = include_once self::SCHEMA_FILE;
            foreach ($tables as $table) {
                foreach ($table->dropSql($connection) as $sql) {
                    try {
                        $connection->execute($sql);
                    } catch (PDOException $exception) {
                    }
                }
                foreach ($table->createSql($connection) as $sql) {
                    try {
                        $connection->execute($sql);
                    } catch (PDOException $exception) {
                        return $this->__cleanAndReturn();
                    }
                }
            }

            // Save first user and first settings
            $Users = $this->getTableLocator()->get('Users');
            $Settings = $this->getTableLocator()->get('Settings');

            $user = $Users->newEntity($this->request->getData('User') + ['role' => 'admin']);
            $setting = $Settings->newEntity($this->request->getData('Setting') + ['enable_auto_conv' => $libavtools]);

            $userSaved = $Users->save($user);
            $settingSaved = $Settings->save($setting, ['associated' => 'Rootpaths']);

            if (!$userSaved || !$settingSaved) {
                $form->setErrors(['User' => $user->getErrors(), 'Setting' => $setting->getErrors()]);
                $this->Flash->error(__('Unable to save your data.'));

                return $this->__cleanAndReturn();
            }

            $this->Flash->success(__('Installation successful!'));

            return $this->redirect(['controller' => 'songs', 'action' => 'import']);
        }
    }

    private function __cleanAndReturn()
    {
        $config_file = new File(self::CONFIG_FILE);
        $config_file->delete();
        return null;
    }
}
