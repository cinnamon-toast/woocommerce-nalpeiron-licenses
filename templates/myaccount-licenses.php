<?php
/**
 * Product License Codes
 */
?>

<div class="woocommerce_account_subscriptions">
	<h2><?php _e( 'Product License Codes', 'woocommerce-subscriptions' ); ?></h2>
	<?php if ( ! empty( $licenses ) ) : ?>
	<table class="shop_table shop_table_responsive my_account_subscriptions my_account_orders">

		<thead>
			<tr>
		 		<th><?php _e('License Number', 'ngrain'); ?></th>
		 		<th style="text-align:center"><?php _e('Product', 'ngrain'); ?></th>
		 		<th style="text-align:center"><?php _e('Order', 'ngrain'); ?></th>
		 		<th style="text-align:center"><?php _e('Activation Date', 'ngrain'); ?></th>
		 		<th style="text-align:center"><?php _e('Expiry Date', 'ngrain'); ?></th>
		 		<th style="text-align:center"><?php _e('Last Renewal', 'ngrain'); ?></th>
	 		</tr>
		</thead>

		<tbody>
		<?php 
		foreach ( $licenses as $license ) : 
			$order = wc_get_order($license->order_id);
			?>
			<tr>
 				<td><?php echo $license->license_code; ?></td>
 				<td style="text-align:center;width:250px">
 					<?php $product = get_post($license->product_id); ?>
 					<a href="<?php echo get_post_permalink( $product->ID ); ?>">
 						<?php echo get_the_title($product->ID); ?>
 					</a>
 				</td>
 				<td style="text-align:center;width:140px">
 					<a href="<?php echo $order->get_view_order_url(); ?>">
						#<?php echo $order->get_order_number(); ?>
					</a>
 				</td>
 				<td style="width:140px;text-align:center"><?php echo date('F d, Y', strtotime($license->activation_date)); ?></td>
 				<td style="width:140px;text-align:center"><?php echo date('F d, Y', strtotime($license->expiration_date)); ?></td>
 				<td style="width:140px;text-align:center">
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

	<?php else : ?>
		<p class="no_subscriptions">
			<?php printf( __( 'You have no product license codes.') ); ?>
		</p>
	<?php endif; ?>

</div>