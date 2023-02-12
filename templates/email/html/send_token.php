<table width="630" align="center" border="0">
    <tbody>
    <tr width="630">
        <td style="font-size: 24px;">
            <?php echo __('Reset your password'); ?>
        </td>
    </tr>
    <tr width="630">
        <td style="padding-top: 25px;">
            <?php echo __('Hi, you receive this email because you\'ve requested a password reset on Sonerezh.'); ?>
			<?php echo __(
					'Please follow this {0} or copy and paste the following URL in your browser: {1}',
					$this->Html->link(__('link'), array(
						'controller' => 'users',
						'action' => 'resetPassword',
						'?' => array('t' => $token),
						'_full' => true
					)),
					$this->Url->build(array(
						'controller' => 'users',
						'action' => 'resetPassword',
						'?' => array('t' => $token),
						'_full' => true
					))
			); ?>
        </td>
    </tr>
    </tbody>
</table>
