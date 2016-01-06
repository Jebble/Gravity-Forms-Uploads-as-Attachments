<?php
/*
Plugin Name: Gravity Forms - Uploads as Attachments
Plugin URI: https://github.com/Jebble/Gravity-Forms-Uploads-as-Attachments
Description: Adds an option to send files from the fileupload field(s) as attachment with notifications
Version: 1.0.0
Author: Jebble
Author URI: http://jebble.nl/
Text Domain: jbl_gfuaa
*/

add_action( 'admin_init', 'jbl_check_gforms' );
function jbl_check_gforms() {
    if ( is_admin() && !is_plugin_active( 'gravityforms/gravityforms.php' ) ) {
        add_action( 'admin_notices', function() {
        	echo '<div class="error"><p><strong>Gravity Forms - Uploads as Attachments</strong> requires <a href="http://www.gravityforms.com/" target="_blank"><em>Gravity Forms by Rocketgenius, Inc.</em></a> to work.</p></div>';
        } );
    }
}


/**
 * Gravity Forms Notification UI Settings
 *
 * Add custom settings to the notification page in Gravity Forms
 */
add_filter( 'gform_notification_ui_settings', 'jbl_add_gform_notification_settings', 10, 3 );
function jbl_add_gform_notification_settings( $ui_settings, $confirmation, $form ) {

	$checked_enable = '';
	$checked_delete_files_after = '';

	if( isset( $confirmation['jbl_gfuaa_enable'] ) && $confirmation['jbl_gfuaa_enable'] == 1 ) {
		$checked_enable = ' checked="checked"';
	}

	if( isset( $confirmation['jbl_gfuaa_delete_files_after'] ) && $confirmation['jbl_gfuaa_delete_files_after'] == 1 ) {
		$checked_delete_files_after = ' checked="checked"';
	}

	$ui_settings['jbl_uaa_settings'] = '
		<tr valign="top">
			<th colspan=2>
				<hr>
				<strong>Uploads as Attachments settings: </strong>
			</th>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="jbl_gfuaa_enable">' . __( 'Enable', 'jbl_gfuaa' ) . '</label>
			</th>
			<td>
				<input type="checkbox" id="jbl_gfuaa_enable" name="jbl_gfuaa_enable" value="1"' . $checked_enable . '>
				<label for="jbl_gfuaa_enable" class="inline">' . __( 'Add fileupload fields from this form as attachments to this notification', 'jbl_gfuaa' ) . '</label>
				<br>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row">
				<label for="jbl_gfuaa_delete_files_after">' . __( 'Delete files', 'jbl_gfuaa' ) . '</label>
			</th>
			<td>
				<input type="checkbox" id="jbl_gfuaa_delete_files_after" name="jbl_gfuaa_delete_files_after" value="1"' . $checked_delete_files_after . '>
				<label for="jbl_gfuaa_delete_files_after" class="inline">' . __( 'Delete uploaded files from server after notification sent?', 'jbl_gfuaa' ) . '</label>
				<br>
			</td>
		</tr>
	';

    return $ui_settings;
}


/**
 * Save custom settings to the notification
 */
add_filter( 'gform_pre_notification_save', 'jbl_save_gform_notification_settings', 10, 2 );
function jbl_save_gform_notification_settings( $notification, $form ) {

	if( isset( $_POST['jbl_gfuaa_enable'] ) ) {
    	$notification['jbl_gfuaa_enable'] = rgpost( 'jbl_gfuaa_enable' );
	}

	if( isset( $_POST['jbl_gfuaa_delete_files_after'] ) ) {
    	$notification['jbl_gfuaa_delete_files_after'] = rgpost( 'jbl_gfuaa_delete_files_after' );
	}

    return $notification;
}


/**
 * Add the fileupload files as attachments if the option is enabled for this notification
 */
add_filter( 'gform_notification', 'jbl_gfuaa_maybe_add_attachments', 10, 3 );
function jbl_gfuaa_maybe_add_attachments( $notification, $form, $entry ) {

	if( isset( $notification['jbl_gfuaa_enable'] ) && $notification['jbl_gfuaa_enable'] == 1 ) {

		$fileuploadfields = GFCommon::get_fields_by_type( $form, array( 'fileupload' ) );

		if( is_array( $fileuploadfields ) && ! empty( $fileuploadfields ) ) {

			$attachments = array();
			$upload_root = RGFormsModeL::get_upload_root();

			foreach ( $fileuploadfields as $field ) {
				$url = $entry[ $field['id'] ];
			}

			if ( empty( $url ) ) {
                continue;
            }
            elseif ( $field['multipleFiles'] ) {
                $uploaded_files = json_decode( stripslashes( $url ), true );
                foreach ( $uploaded_files as $uploaded_file ) {
                    $attachment = preg_replace( '|^(.*?)/gravity_forms/|', $upload_root, $uploaded_file );
                    $attachments[] = $attachment;
                }
            } else {
                $attachment = preg_replace( '|^(.*?)/gravity_forms/|', $upload_root, $url );
                $attachments[] = $attachment;
            }

            $notification['attachments'] = $attachments;
		}

		if( isset( $notification['jbl_gfuaa_delete_files_after'] ) && $notification['jbl_gfuaa_delete_files_after'] == 1 ) {
			add_action( 'gform_after_email', 'jbl_gfuaa_delete_files', 10, 12 );
		}
	}

	return $notification;
}


/**
 * Delete uploaded files from server after sending the notification if the option is enabled.
 */
function jbl_gfuaa_delete_files( $is_success, $to, $subject, $message, $headers, $attachments, $message_format, $from, $from_name, $bcc, $reply_to, $entry ) {

	if( $is_success ) {
		foreach ( $attachments as $attachment ) {
			unlink( $attachment );
		}
	}
}