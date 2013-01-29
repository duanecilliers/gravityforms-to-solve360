<div class="wrap">

	<form method="post" id="gf_s360_export">

	<div id="icon-options-general" class="icon32"><br></div>
	<h2>Gravity Forms to Solve360 Export Options</h2>

	<table>

		<tr>
			<td>
				<h3>Debug details</h3>
			</td>
			<td>
			</td>
		</tr>

		<tr>

			<td>
				Debug mode
			</td>
			<td>
			<?php $gf_s360_export_debug_mode = (get_option('gf_s360_export_debug_mode') == 'true'); ?>
				<input type="radio" name="gf_s360_export_debug_mode" value="true" id="gf_s360_export_debug_mode_enabled" <?php if($gf_s360_export_debug_mode) echo 'checked="checked" '; ?> />
				&nbsp; <label for="gf_s360_export_debug_mode_enabled">On</label> &nbsp; &nbsp;

				<input type="radio" name="gf_s360_export_debug_mode" value="false" id="gf_s360_export_debug_mode_disabled" <?php if(!$gf_s360_export_debug_mode) echo 'checked="checked" '; ?> />
				&nbsp; <label for="gf_s360_export_debug_mode_disabled">Off</label> &nbsp; &nbsp;
			</td>
		</tr>

		<tr>
			<td>
				<h3>Solve details</h3>
			</td>
			<td>
			</td>
		</tr>

		<tr>

			<td>
				<label for="gf_s360_export_user">Solve360 User</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_user" id="gf_s360_export_user" value="<?php echo get_option('gf_s360_export_user'); ?>" />
			</td>
		</tr>

		<tr>

			<td>
				<label for="gf_s360_export_token">Solve360 API Token</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_token" id="gf_s360_export_token" value="<?php echo get_option('gf_s360_export_token'); ?>" />
			</td>
		</tr>

		<tr>
			<td>
				<h3>Solve notification details</h3>
			</td>
			<td>
				<p>user@example.com, Another User &lt;anotheruser@example.com&gt;</p>
			</td>
		</tr>

		<tr>
			<td>
				<label for="gf_s360_export_to">To:</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_to" id="gf_s360_export_to" value="<?php echo get_option('gf_s360_export_to'); ?>" />
			</td>
		</tr>

		<tr>
			<td>
				<label for="gf_s360_export_from">From:</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_from" id="gf_s360_export_from" value="<?php echo get_option('gf_s360_export_from'); ?>" />
			</td>
		</tr>

		<tr>
			<td>
				<label for="gf_s360_export_cc">CC:</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_cc" id="gf_s360_export_cc" value="<?php echo get_option('gf_s360_export_cc'); ?>" />
			</td>
		</tr>

		<tr>
			<td>
				<label for="gf_s360_export_bcc">Bcc:</label>
			</td>
			<td>
				<input type="text" class="regular-text" name="gf_s360_export_bcc" id="gf_s360_export_bcc" value="<?php echo get_option('gf_s360_export_bcc'); ?>" />
			</td>
		</tr>

	</table>

	<p class="submit" style="text-align: left;">
		<input type="submit" name="submit" value="Save Settings" class="button-primary"/>
	</p>

	<?php wp_nonce_field( 'gf_s360_export_edit', 'gf_s360_export_nonce' ); ?>

	</form>

</div>
