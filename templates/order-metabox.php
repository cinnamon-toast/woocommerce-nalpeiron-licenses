<p><?php _e('Total Licenses Assigned'); ?>: <?php echo count($licenses); ?></p>
<table class="wp-list-table widefat fixed posts">
	<thead>
		<tr>
			<th style="width:5%;">#</th>
			<th style="width:35%;"><?php _e('License Code'); ?></th>
			<th style="width:40%;"><?php _e('Product'); ?></th>
			<th style="width:20%;"><?php _e('Order Date'); ?></th>	
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
			wp_reset_query();
			$query = new WP_Query(array(
				'post_type' => 'product',
				'p' => $l->product_id,
				'posts_per_page' => 1
			));
			if ( $query -> have_posts() ) : 
				while ( $query -> have_posts() ) :
					$query -> the_post(); 
					?>
					<td>
						<a href="/wp-admin/post.php?post=<?php echo $l->product_id; ?>&action=edit">
							<?php the_title(); ?>
						</a>
					</td>
				<?php 
				endwhile;
			endif;
			wp_reset_query();
			if ( intval($l->order_id, 10) > 0 ) :
				$order = new WC_Order($l->order_id);
				if ( $order->customer_user > 0 ) :
					$order_user = get_userdata($order->customer_user);	
					?>	
					<td>
						<?php echo $order->order_date; ?>
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

