<div class="wrap" id="kanban_Ninjaforms">
	<h1>
		Kanban + Ninja Forms
	</h1>

	<?php if ( !function_exists( 'Ninja_Forms' ) ) : ?>
		<div class="notice notice-error">
			<p>
				<?php echo sprintf(
					__('Please install and activate %s.', 'kanban'),
					'<a href="https://ninjaforms.com" target="_blank">Ninja Forms</a>'
				)
				?>
			</p>
		</div>
	<?php endif ?>

	<?php if ( isset($_GET['notice']) ) : ?>
	<div class="updated">
		<p><?php echo $_GET['notice'] ?></p>
	</div>
	<?php endif // notice ?>



	<?php if ( function_exists( 'Ninja_Forms' ) ) : ?>
	<p>
		<?php echo __('Choose a Ninja Form form to edit', 'kanban') ?>:
		<select id="select-gform" autocomplete="off">
			<option value="">-- <?php echo __('Choose a form', 'kanban') ?> --</option>
			<?php foreach ( $forms as $form ) : ?>
				<option value="<?php echo $form->get_id() ?>">
					<?php echo $form->get_setting('title') ?>
				</option>
			<?php endforeach; // $forms ?>
		</select>
	</p>

	<hr>
	<?php endif ?>



	<form action="" method="post">

		<?php wp_nonce_field( self::$slug, self::$slug . '-nonce' ) ?>

		<?php $i = 0;
		foreach ( $forms as $form ) : ?>
			<div class="gform" id="gform-<?php echo $form->get_id() ?>" style="display: none;">
				<h2>
					<small><?php echo __('Form', 'kanban') ?>:</small>
					<br>
					<b style="font-size: 1.618em">
						<?php echo $form->get_setting('title') ?>
					</b>
				</h2>

				<p>
					1.
					<?php echo __('Choose a board to sync with', 'kanban') ?>:
					<?php if ( count($boards) > 1 ) : ?>
					<select name="forms[<?php echo $form->get_id() ?>][board]" class="board_id" autocomplete="off">
						<option value="">-- <?php echo __('Choose a board', 'kanban') ?> --</option>
						<?php foreach ( $boards as $board ) : ?>
							<option
								value="<?php echo $board->id ?>" <?php echo isset( $saved[ $form->get_id() ][ 'board' ] ) && $saved[ $form->get_id() ][ 'board' ] == $board->id ? 'selected' : '' ?>>
								<?php echo $board->title ?>
							</option>
						<?php endforeach; // $forms ?>
					</select>
					<?php else : $board = reset($boards) ?>
						<input name="forms[<?php echo $form->get_id() ?>][board]" type="hidden" value="<?php echo $board->id ?>" class="board_id">
						<input type="text" value="<?php echo esc_attr($board->title) ?>" readonly>
					<?php endif ?>
				</p>

				<p>
					2.
					<?php echo __('Map form fields to task data', 'kanban') ?>:
				</p>

				<table style="background: white; min-width: 50%;">
					<tbody>
					<?php $fields = Ninja_Forms()->form( $form->get_id() )->get_fields(); foreach ( $fields as $field ) : ?>
						<tr class="<?php echo $i % 2 == 0 ? '' : 'alternate' ?>">
							<td>
								<?php echo $field->get_setting('label') ?>
							</td>
							<td>
								&rarr;
							</td>
							<td>
								<select
									name="forms[<?php echo $form->get_id() ?>][<?php echo $field->get_id() ?>][table_column]"
									data-defaultValue="forms[<?php echo $form->get_id() ?>][<?php echo $field->get_id() ?>][defaultValue]"
									data-value="<?php echo isset( $saved[ $form->get_id() ][ $field->get_id() ][ 'defaultValue' ] ) ? $saved[ $form->get_id() ][ $field->get_id() ][ 'defaultValue' ] : '' ?>"
									autocomplete="off" style="width: 100%;" class="table_column">
									<option value="">
										-- <?php echo __('Map to', 'kanban') ?> --
									</option>
									<?php foreach ( $table_columns as $id => $label ) : ?>
										<option
											value="<?php echo $id ?>" <?php echo isset( $saved[ $form->get_id() ][ $field->get_id() ] ) && $saved[ $form->get_id() ][ $field->get_id() ][ 'table_column' ] == $id ? 'selected' : '' ?>>
											<?php echo $label ?>
										</option>
									<?php endforeach; // $table_columns ?>
								</select>

								<?php // endforeach; // $table_columns ?>
							</td>
							<td>

								<?php if ( $field->get_setting( 'type' ) == 'hidden' ) : ?>
									<select
										name="forms[<?php echo $form->get_id() ?>][<?php echo $field->get_id() ?>][defaultValue]"
										autocomplete="off" style="width: 100%;" class="">
									</select>
								<?php endif ?>
							</td>
						</tr>
						<?php $i++; endforeach; // $field ?>
					</tbody>
				</table>
			</div><!-- form -->
		<?php endforeach; // $forms ?>


		<?php if ( function_exists( 'Ninja_Forms' ) ) : ?>
		<?php submit_button( __('Save your settings', 'kanban') ); ?>
		<?php endif ?>
	</form>

	<div style="display: none">
		<?php foreach ( $boards as $board ) : ?>
			<select id="project_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->projects as $project ) : ?>
					<option
						value="<?php echo $project->id ?>">
						<?php echo $project->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="status_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->statuses as $status ) : ?>
					<option
						value="<?php echo $status->id ?>">
						<?php echo $status->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="estimate_id<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->estimates as $estimate ) : ?>
					<option
						value="<?php echo $estimate->id ?>">
						<?php echo $estimate->title ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="user_id_assigned<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->users as $user ) : ?>
					<option
						value="<?php echo $user->ID ?>">
						<?php echo $user->long_name_email ?>
					</option>
				<?php endforeach; ?>
			</select>

			<select id="user_id_author<?php echo $board->id ?>">
				<option value="">-- <?php echo __('Choose one', 'kanban') ?> --</option>
				<?php foreach ( $board->users as $user ) : ?>
					<option
						value="<?php echo $user->ID ?>">
						<?php echo $user->long_name_email ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php echo apply_filters('kanban_Ninja_forms_extra_selects', '', $board->id) ?>

		<?php endforeach; // $forms ?>
	</div>


</div><!-- wrap -->


<script>
	jQuery( function ( $ ) {
		$( '#select-gform' ).on(
			'change',
			function () {
				var $select = $( this );
				var form_id = $select.val();

				var $form_to_show = $( '#gform-' + form_id );
				$( '.gform' ).not( $form_to_show ).hide();
				$form_to_show.show();
			}
		);

		$( '.table_column' ).on(
			'change',
			function () {
				console.log('test');
				var $select = $( this );
				var defaultValue = $select.attr( 'data-defaultValue' );
				var $defaultValue = $( '[name="' + defaultValue + '"]' );
				var $wrapper = $select.closest( '.gform' );
				var $board_id = $( '.board_id', $wrapper );

				var field = $select.val();
				var board_id = $board_id.val();
				console.log('#' + field + board_id);
				var $template = $( '#' + field + board_id );

				if ( $template.length == 0 ) {
					$defaultValue.empty().hide();
					return false;
				}

				var $options = $template.html();
				$defaultValue.html( $options ).show();
			}
		);

		$( '.table_column' )
		.trigger( 'change' )
		.each( function () {
			var $select = $( this );
			var defaultValue = $select.attr( 'data-defaultValue' );
			var $defaultValue = $( '[name="' + defaultValue + '"]' );

			var val = $( this ).attr( 'data-value' );

			if ( typeof val === 'undefined' || val == '' ) {
				return;
			}

			$defaultValue.val( val );
		} );
	} );
</script>

<style>
	#kanban_Ninjaforms table {
		border-collapse: collapse;
	}

	#kanban_Ninjaforms td {
		padding: 10px;
	}
</style>