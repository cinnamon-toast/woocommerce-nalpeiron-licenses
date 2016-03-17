<p><?php _e('Total Licenses Assigned'); ?>: <?php echo count($licenses); ?></p>
<table class="wp-list-table widefat fixed posts">
	<thead>
		<tr>
			<th style="width:10%;">#</th>
			<th><?php _e('License Code'); ?></th>
			<th style="width:10%;"><?php _e('Order'); ?></th>
			<th><?php _e('Order Date'); ?></th>	
			<th><?php _e('Customer User'); ?></th>	
		</tr>
	</thead>
	<tbody>
	<?php 
	if ( count($licenses) ) : 
		$i = 1; 
		foreach ($licenses as $l) : ?>
		<tr>
			<td><?php echo $i; ?></td>
			<td><?php echo $l->license_code; ?></td>
			<?php 
			if ( intval($l->order_id, 10) > 0 ) :
				$order = new WC_Order($l->order_id);
				?>
				<td>
					<a href="/wp-admin/post.php?post=<?php echo $order->id; ?>&action=edit">
						<?php echo $order->id; ?>
					</a>
				</td>
				<td>
					<?php echo $order->order_date; ?>
				</td>
				<?php 
				if ( $order->customer_user > 0 ) :
					$order_user = get_userdata($order->customer_user);	
					?>		
					<td>
						<a href="/wp-admin/user-edit.php?user_id=<?php echo $order->customer_user; ?>"><?php echo $order_user->user_login; ?>
					</td>	
				<?php else: ?>
					<td>Guest</td>
				<?php endif; ?>
			<?php else : ?>
				<td colspan="6">No Details</td>
			<?php endif; ?>	
		</tr>
		<?php 
		$i++; 
		endforeach;
	else: ?>
		<tr>
			<td colspan="3">
				<?php _e('No licenses available for this product.'); ?>
			</td>
		</tr>
	<?php endif; ?>
	</tbody>
</table>

