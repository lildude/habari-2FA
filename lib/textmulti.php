<?php if ( !defined( 'HABARI_PATH' ) ) { die('No direct access'); } ?>
<div<?php
		echo $control->parameter_map(
			array(
				'class', 'id' => 'name'
			)
		); ?>>
	<span class="pct25"><label><?php echo $control->caption; ?></label></span>
	<span class="pct25">
<?php
if ( !is_array( $control->value ) ) {
	$value = array( $control->value );
}
$i = 0;
foreach( $value as $key => $value_1 ) :
$i++;
	if ( $value_1 ) :
 ?>
	 
	<span class="textmulti_item">
		<label><?php echo $key; ?></label>
		
		<input type="hidden" <?php
		echo $control->parameter_map(
			array(
				'tabindex', 'size', 'maxlength', 'autocomplete', 'disabled', 'readonly',
			),
			array(
				'name' => $control->field . "[{$key}]",
				'id' => $control->field . '_' . $i,
				'value' => Utils::htmlspecialchars( $value_1 ),
			)
		);
		?>>	<a href="#" onclick="return controls.textmulti.remove( this );" title="<?php _e( 'remove' ); ?>" class="textmulti_remove opa50">[<?php _e( 'remove' ); ?>]</a></span>
<?php
	endif;
endforeach;
?>
	</span>
<?php

	if ( isset( $control->helptext ) && !empty( $control->helptext ) ) {
		?>
			<span class="pct40 helptext"><?php echo $helptext; ?></span>
		<?php
	}

?>
	<?php $control->errors_out( '<li>%s</li>', '<ul class="error">%s</ul>' ); ?>
</div>