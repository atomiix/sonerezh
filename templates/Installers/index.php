<?php

$templates = [
    'label' => '<label class="col-sm-3 control-label"{{attrs}}>{{text}}</label>',
    'formGroup' => '{{label}}<div class="col-sm-9">{{input}}{{error}}</div>',
    'inputContainerError' => '<div class="form-group {{type}}{{divClass}}{{required}} error">{{content}}</div>',
];

$this->Form->setTemplates($templates);
$this->start('script');
echo $this->fetch('script'); ?>
<script>
    $(function() {
        $('#db-datasource').selecter({
            label: "<?php echo __('Select a database type'); ?>"
        });
        $('#db-datasource').change(function() {
            if ($(this).val() == "sqlite") {
                $('.sqlite-optional').hide().find('input').removeAttr('required');
            } else {
                $('.sqlite-optional').show().find('input:not(#db-prefix,#db-password)').attr('required', 'required');
            }
        }).change();
    });
</script>
<?php $this->end(); ?>

<div class="col-xs-12" style="margin-bottom: 20px;">
    <div class="page-header">
        <h1><?php echo __('Sonerezh'); ?></h1>
    </div>
    <p>
        <?php echo __("Welcome on the Sonerezh's installation page. Just fill in the information below and you'll be on your way to listening to your favorite songs!"); ?>
    </p>

    <h2><?php echo __('Requirements'); ?></h2>
    <hr />

    <?php foreach ($requirements as $requirement): ?>
        <div class="alert alert-<?php echo $requirement['label']; ?>">
            <?php echo $requirement['message']; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!$missing_requirements): ?>

    <h2><?php echo __('Database configuration'); ?></h2>
    <hr />

    <p>
        <?php echo __("Please provide the following information to allow Sonerezh to access its database."); ?> <span class="text-danger"><?php echo __('Note that if you are reinstalling Sonerezh, all your previous data will be lost.') ?></span>
    </p>


    <?php echo $this->Form->create($form, array(
        'class' => 'form-horizontal',
    )); ?>

    <div class="col-xs-8 col-xs-offset-2">
        <?php
        echo $this->Form->control('DB.datasource', array(
            'options'   => $available_drivers,
            'label'     => array('text' => __('Database type'), 'style' => 'padding-top: 20px;'),
            'required'
        ));
        ?>
        <div class="sqlite-optional">
            <?php
            echo $this->Form->control('DB.host', array('placeholder' => __('You can specify a non standard port if needed (127.0.0.1:1234)'), 'required'));
            ?>
        </div>
        <?php
        echo $this->Form->control('DB.database', array('placeholder' => __('Database name'), 'required'));
        ?>
        <div class="sqlite-optional">
            <?php
            echo $this->Form->control('DB.login', array('placeholder' => __('Database user login'), 'required' => true));
            echo $this->Form->control('DB.password', array('placeholder' => __('Database user password'), 'required' => false));
            echo $this->Form->control('DB.prefix', array('placeholder' => __('Leave empty if none'), 'label' => array('text' => __('Prefix (optional)'), 'class' => 'col-sm-3 control-label')));
            ?>
        </div>
    </div>

    <div class="clearfix"></div>


    <h2><?php echo __('Information needed'); ?></h2>
    <hr />

    <p>
        <?php echo __("Please provide the following information. Don't worry, you can always change these settings later."); ?>
    </p>

    <div class="col-xs-8 col-xs-offset-2">

        <?php
        echo $this->Form->control('User.email', array('placeholder' => 'john.doe@sonerezh.bzh', 'required'));
        echo $this->Form->control('User.password', array('placeholder' => __('Password'), 'label' => array('text' => __('Password (twice)'), 'class' => 'col-sm-3 control-label'), 'required'));
        echo $this->Form->control('User.confirm_password', array('placeholder' => __('Confirm your password'), 'type' => 'password', 'label' => array('text' => '', 'class' => 'col-sm-3 control-label'), 'required'));
        echo $this->Form->control('Setting.rootpaths.0.rootpath', array('placeholder' => '/home/jdoe/Music', 'label' => array('text' => 'Music folder', 'class' => 'col-sm-3 control-label'), 'after' => '<small><span class="help-block"><i class="glyphicon glyphicon-info-sign"></i> Current App folder is: '.APP.'</span></small>'));
        echo $this->Form->submit('Run!', array('class' => 'btn btn-success pull-right'));
        ?>
    </div>

    <?php echo $this->Form->end([]); ?>
    <?php endif; ?>
</div>
