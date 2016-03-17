<?php
/**
 * Order License Codes
 */
?>

<table class="order-info-table two-columns">
 	<tbody>
 		<tr>
	 		<td colspan="4">
	 			<h3><?php _e( 'License Codes', 'woocommerce' ); ?></h3>
	 		</td>
	 	</tr>
	</tbody>
	<tbody class="info-header-two">
 		<tr>
	 		<th style="padding: 10px 0"><?php _e('License Number', 'ngrain'); ?></th>
	 		<th class="center" style="padding: 10px 0"><?php _e('Activation Date', 'ngrain'); ?></th>
	 		<th class="center" style="padding: 10px 0"><?php _e('Expiry Date', 'ngrain'); ?></th>
	 		<th class="center" style="padding: 10px 0"><?php _e('Last Renewal', 'ngrain'); ?></th>
 		</tr>
 	</tbody>
 	<tbody>
 		<?php foreach ($licenses as $license) : ?>
 			<tr>
 				<td style="padding: 3px 0"><?php echo $license->license_code; ?></td>
 				<td class="center" style="width:200px;padding: 3px 0"><?php echo date('F d, Y', strtotime($license->activation_date)); ?></td>
 				<td class="center" style="width:200px;padding: 3px 0"><?php echo date('F d, Y', strtotime($license->expiration_date)); ?></td>
 				<td class="center" style="width:200px;padding: 3px 0">
 					<?php 
 					if ($license->latestrenewal_date != null) {
 						echo date('F d, Y', strtotime($license->latestrenewal_date)); 
 					} else {
 						echo '-';
 					}
 					?>
 				</td>
 			</tr>
 		<?php endforeach; ?>
 	</tbody>
</table>