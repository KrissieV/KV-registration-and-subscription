<?php
/*
  Plugin Name: Standards Subscription
  Plugin URI: http://honeystreet.com
  Description: Gives subscriber the ability to recieve updates to Standards based on Preferences. Preferences are managed on the front end via Gravity Forms and it's User Registration Add-on, both Gravity Forms and the User Registration Add-ons are required plugins.
  Version: 1.0
  Author: Krissie VandeNoord, Honeystreet Design Studio
  Author URI: http://honeystreet.com
 */
 
 /* 
 *
 * Subscription Functionality
 *	
*/

// Create email to be sent
function honeystreet_email_build($post) {
	// Get Sport terms and title of Standard
	$terms = get_the_terms( $post->ID, 'sport' );
	$title = get_the_title($post->ID);
	$from = 'krissie@honeystreet.com'; // from/to/reply-to email used for sending
	
	// Create an Array for subcriber emails
	    $emails = array ();
	
    // Filter subcribers based on subscription preferences that match this Standard
    // if they are subscribed by Sport
    if ( $terms && ! is_wp_error( $terms ) ) : 
	
		$sports = array();
		
		foreach ( $terms as $term ) {
			$sports[] = $term->name;
			$metakey = $term->slug;
			$metavalue = $term->name;
			$subscribers = get_users(array('meta_key' => $metakey, 'meta_value' => $metavalue, 'meta_compare' => '='));
			foreach ( $subscribers as $subscriber )
				$emails[] = $subscriber->user_email;	
		}
							
		$sports_list = join( ", ", $sports );
		
	endif;
	
	// if they are subscribed to the individual standard
	$subscribers = get_users();
	foreach ( $subscribers as $subscriber ) {
		$metavalue = get_the_author_meta( 'individual_standards', $subscriber->ID);
		
		if (strpos( $metavalue, $title) || $metavalue === $title) {
			$emails[] = $subscriber->user_email;
		}
		
	}
				  
    // The subject and message of email that will be sent    
    $subject = 'Standard added or updated for ' . $sports_list;

	$body = '<p>A standard has been updated or added for ' . $sports_list . '.</p>
	<p><strong><a href="' . get_permalink( $post ) . '">' . $title . '</a></strong></p><p>You are receiving this email because you have subscribed to updates, to unsubscribe or manage your preferences, visit xxxxx.org/subscription-preferences and login.</p>';
    
	// Send Email
    wp_mail( 
	    // Send it to yourself, BCC subscribers
	    $from, 
	    $subject, 
	    $body, 
	    // extra headers
	    array (
	        'Bcc:' . implode( ",", $emails ),
	        'From:' . $from,
	        'Content-type: text/html'
	    ) 
	);

}

// Change default email from name
add_filter( 'wp_mail_from_name', 'honeystreet_wp_mail_from_name' );
function honeystreet_wp_mail_from_name( $original_email_from ) {
	return 'Your Business';
}

// Send email when the status of a Standard changes from 'Draft' or 'Pending Review' to 'Published'
add_action( 'transition_post_status', 'send_mails_on_publish', 10, 3 );

function send_mails_on_publish( $new_status, $old_status, $post )
{
	// Don't execute if status was already 'publish' if the new status is NOT 'publish' or if we are not editing a 'Standard' CPT
    if ( 'publish' !== $new_status or 'publish' === $old_status
        or 'standard' !== get_post_type( $post ) )
        return;
        
        honeystreet_email_build($post);
	    
}

// Add checkbox to publish box within an Standard Edit
add_action('post_submitbox_misc_actions','honeystreet_email_update_option');
function honeystreet_email_update_option($post) {
	
	// Do not execute if not a Standard CPT
	if ( 'standard' !== get_post_type( $post ) )
        return;

	echo '<div class="misc-pub-section misc-pub-section-last"><label><input type="checkbox" value="1" name="send_update_email" /> Send Update Email to Subscribers</label></div>';
};

// Send email if 'Send Update Email to Subscribers' box is checked
add_action( 'transition_post_status', 'send_mails_on_checkbox_selected', 10, 3 );
function send_mails_on_checkbox_selected($post) {
	if ($_POST['send_update_email'] == '1') {
	honeystreet_email_build($post);
	}
}

 /* 
 *
 * Dynamically Populate Subscription Preferences Form
 *	
*/

add_filter( 'gform_pre_render_5', 'populate_standards' );
add_filter( 'gform_pre_validation_5', 'populate_standards' );
add_filter( 'gform_pre_submission_filter_5', 'populate_standards' );
add_filter( 'gform_admin_pre_render_5', 'populate_standards' );
function populate_standards( $form ) {

    foreach ( $form['fields'] as &$field ) {

        if ( strpos( $field->cssClass, 'standards-select' ) === false ) {
            continue;
        }

        // you can add additional parameters here to alter the posts that are retrieved
        // more info: [http://codex.wordpress.org/Template_Tags/get_posts](http://codex.wordpress.org/Template_Tags/get_posts)
        $posts = get_posts( 'numberposts=-1&post_status=publish&post_type=standard' );

        $choices = array();

        foreach ( $posts as $post ) {
            $choices[] = array( 'text' => $post->post_title, 'value' => $post->post_title );
        }

        // update 'Select a Post' to whatever you'd like the instructive option to be
        $field->placeholder = 'Select a Standard';
        $field->choices = $choices;

    }

    return $form;
}

add_filter( 'gform_pre_render_5', 'populate_checkbox' );
add_filter( 'gform_pre_validation_5', 'populate_checkbox' );
add_filter( 'gform_pre_submission_filter_5', 'populate_checkbox' );
add_filter( 'gform_admin_pre_render_5', 'populate_checkbox' );
function populate_checkbox($form){
	
	foreach ( $form['fields'] as &$field ) {

        if ( strpos( $field->cssClass, 'sports-select' ) === false ) {
            continue;
        }

        // you can add additional parameters here to alter the posts that are retrieved
        // more info: [http://codex.wordpress.org/Template_Tags/get_posts](http://codex.wordpress.org/Template_Tags/get_posts)
        $terms = get_terms( 'sport' );

        $sports = array();

        foreach ( $terms as $term ) {
            $sports[] = array( 'text' => $term->name, 'value' => $term->slug, );
        }

        // update 'Select a Post' to whatever you'd like the instructive option to be
        
        $field->choices = $sports;

    }

    return $form;
}

 /* 
 *
 * Add subscription preferences to user profiles on the backend so an admin can manage when needed.
 *	
*/

add_action( 'show_user_profile', 'honeystreet_show_extra_profile_fields' );
add_action( 'edit_user_profile', 'honeystreet_show_extra_profile_fields' );

function honeystreet_show_extra_profile_fields( $user ) { ?>

	<h3>Subscription Preferences</h3>

	<table class="form-table">

		<tr>
			<th><label for="sport">Sport</label></th>

			<td>

				<?php
				$terms = get_terms( 'sport' );
					
		        if ( $terms && ! is_wp_error( $terms ) ) : 
		
				$sports = array();
			
				foreach ( $terms as $term ) {
					$sports[] = array( 'text' => $term->name, 'value' => $term->slug );
				}
						
				endif;
                
                foreach($sports as $key => $value) {
                    $metakey = $value['value'];
                    $metavalue = get_the_author_meta( $metakey, $user->ID); ?>
                    <label><input type="checkbox" name="<?php echo $metakey; ?>" <?php if ($metavalue == $metakey ) { ?>checked="checked"<?php }?> value="<?php echo $metakey; ?>" /> <?php print $value['text']; ?></label><br />
                <?php } ?>				
			</td>
		</tr>

	</table>
	<table class="form-table">

		<tr>
			<th><label for="sport">Individual Standards</label></th>

			<td>
				<?php
				
				$posts = get_posts( 'numberposts=-1&post_status=publish&post_type=standard' );
					
		         
		
				$standards = array();
			
				foreach ( $posts as $post ) {
					$standards[] = array( 'text' => $post->post_title, 'value' => $post->post_name );
				}
				$metavalues = get_user_meta($user->ID,'individual_standards');
				echo '<p>Control Click to add to already selected items and/or select multiple items in the list.</p>';
                echo '<select name="standards[]" multiple="multiple" data-placeholder="Click to select..." size="7">';
                foreach($standards as $key => $value) { ?>
                    <option value='<?php echo $value['text']; ?>' <?php if (strpos($metavalues[0],$value['text']) !== false) { echo 'selected="selected"'; } ?>><?php echo $value['text']; ?></option>
                    
                <?php } 
                echo '</select>'; ?>			
			</td>
		</tr>

	</table>
<?php }

add_action( 'personal_options_update', 'honeystreet_save_extra_profile_fields' );
add_action( 'edit_user_profile_update', 'honeystreet_save_extra_profile_fields' );

function honeystreet_save_extra_profile_fields( $user_id ) {

	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;
		
		$terms = get_terms( 'sport' );
					
        if ( $terms && ! is_wp_error( $terms ) ) : 

		$sports = array();
	
		foreach ( $terms as $term ) {
			$sports[] = array( 'text' => $term->name, 'value' => $term->slug );
		}
				
		endif;
        
        foreach($sports as $key => $value) {
            $metakey = $value['value'];
            $metavalue = get_the_author_meta( $metakey, $user_id);
            update_user_meta($user_id, $metakey, $_POST[$metakey]);                     
        }
        if ($_POST['standards']) {
        $newstandards = implode(',', $_POST['standards']);
        }
        update_user_meta($user_id,'individual_standards', $newstandards);
}