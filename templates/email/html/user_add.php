<table width="630" align="center" border="0">
    <tbody>
    <tr width="630">
        <td style="font-size: 24px;">
            <?php echo __('Welcome on Sonerezh,'); ?>
        </td>
    </tr>
    <tr width="630">
        <td style="padding-top: 25px;">
            <?php echo __('Your account ({0}) has been successfully created. We hope you will enjoy it :)', $user_email); ?>
            <?php echo $this->Html->link(__('Log me in'), array('controller' => 'users', 'action' => 'login', 'full_base' => true)); ?>
        </td>
    </tr>
    </tbody>
</table>
