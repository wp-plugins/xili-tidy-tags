<?php
/*
Plugin Name: xili-tidy-tags
Plugin URI: http://dev.xiligroup.com/xili-tidy-tags/
Description: xili-tidy-tags is a tool for grouping tags by language or semantic group. Initially developed to enrich xili-language plugin and usable in all sites (CMS).
Author: dev.xiligroup.com - MS
Version: 0.8.2
Author URI: http://dev.xiligroup.com
*/

# 0.8.2 - 090402 - fixes tag_cloud php warning when tagsgroup are unfilled - 
# 0.8.1 - 090331 - some fixes -
# first public release 090329 - 0.8.0 - beta version

# This plugin is free software; you can redistribute it and/or
# modify it under the terms of the GNU Lesser General Public
# License as published by the Free Software Foundation; either
# version 2.1 of the License, or (at your option) any later version.
#
# This plugin is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
# Lesser General Public License for more details.
#
# You should have received a copy of the GNU Lesser General Public
# License along with this plugin; if not, write to the Free Software
# Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA

define('XILITIDYTAGS_VER','0.8.2'); /* used in admin UI */

class xili_tidy_tags {
	
	var $is_metabox = false; /* for tests of special box in post */
	var $is_post_ajax = false; /* for tests using ajax in UI */

	function xili_tidy_tags($metabox = false, $post_ajax = false) {
		$this->is_metabox = $metabox;
		$this->is_post_ajax = $post_ajax;
		
		/*activated when first activation of plug*/
		register_activation_hook(__FILE__,array(&$this,'xili_tidy_tags_activate'));
	
		/*get current settings - name of taxonomy - name of query-tag - 0.9.8 new taxonomy taxolangsgroup */
		$this->xili_settings = get_option('xili_tidy_tags_settings');
		if(empty($this->xili_settings)) {
			$this->xili_tidy_tags_activate();	
			$this->xili_settings = get_option('xili_tidy_tags_settings');
		}
		define('TAXOTIDYTAGS',$this->xili_settings['taxonomy']);
		define('LANGSTAGSGROUPSLUG',$this->xili_settings['tidylangsgroup']);
		define('LANGSTAGSGROUPNAME',$this->xili_settings['tidylangsgroupname']);
		
		/* add new taxonomy in available taxonomies */
		register_taxonomy( TAXOTIDYTAGS, 'term',array('hierarchical' => true, 'update_count_callback' => ''));
		
		/* hooks */	
		
		/* admin settings UI*/
		add_action('init', array(&$this, 'init_textdomain'));
		add_filter('plugin_action_links',  array(&$this,'xili_filter_plugin_actions'), 10, 2);
		
		add_action('admin_menu', array(&$this,'xili_add_pages'));
		
		add_action('add_tag_form', array(&$this,'xili_add_tag_form')); /* to choose a group for a new tag */
		add_action('edit_tag_form', array(&$this,'xili_edit_tag_form'));
		
		add_action('created_term', array(&$this,'xili_created_term'),10,2); /* a new term was created */
		add_action('edited_term', array(&$this,'xili_created_term'),10,2);
		
	}
	
	function xili_tidy_tags_activate() {
		$submitted_settings = array(
			    'taxonomy'			=> 'xili_tidy_tags',
			    'tidylangsgroup'	=> 'tidy-languages-group',
			    'tidylangsgroupname'	=> 'All lang.',
			    'version' 			=> '0.3'
		    );
		update_option('xili_tidy_tags_settings', $submitted_settings);
	}	
	function init_textdomain() {
	/*multilingual for admin pages and menu*/
		load_plugin_textdomain('xili_tidy_tags',PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)),dirname(plugin_basename(__FILE__)));
	}
	
	/**
	 * Add action link(s) to plugins page
	 * 
	 * @since 0.8.0
	 * @author MS
	 * @copyright Dion Hulse, http://dd32.id.au/wordpress-plugins/?configure-link and scripts@schloebe.de
	 */
	function xili_filter_plugin_actions($links, $file){
		static $this_plugin;

		if( !$this_plugin ) $this_plugin = plugin_basename(__FILE__);

		if( $file == $this_plugin ){
			$settings_link = '<a href="admin.php?page=xili-tidy-tags/xili-tidy-tags.php">' . __('Settings') . '</a>';
			$links = array_merge( array($settings_link), $links); // before other links
		}
		return $links;
	}
	
	/**
	 * add in new tag form to choose a group for a new tag
	 *
	 * @since 0.8.0
	 * 
	 *
	 */
	function xili_add_tag_form() { 
		$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		$checkline ='';
		foreach ($listgroups as $group) {
			$checkline .= '<input type="checkbox" id="group-'.$group->term_id.'" name="group-'.$group->term_id.'" value="'.$group->term_id.'" />'.$group->name.'&nbsp;-&nbsp;';
		}
		$checkline .='<br /><br /><small>'.__('© by xili-tidy-tags.','xili_tidy_tags').'</small>';
			echo '<div style="margin:2px; padding:3px; border:1px solid #ccc;"><label>'.__('Tags groups','xili_tidy_tags').':</label><br />'.$checkline.'</div>';	
	}
	
	/**
	 * add in edit tag form to choose a group for a edited tag
	 *
	 * @since 0.8.0
	 * 
	 *
	 */
	function xili_edit_tag_form($tag) { 
		$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		$checkline ='';
		foreach ($listgroups as $group) {
			/* add checked="checked" */
			if (is_object_in_term($tag->term_id,TAXOTIDYTAGS,(int) $group->term_id)) {
					$checked = 'checked="checked"';
				} else {
					$checked = '';
				}
			$checkline .= '<input type="checkbox" name="group-'.$group->term_id.'" id="group-'.$group->term_id.'" value="'.$group->term_id.'" '.$checked.' />'.$group->name.'&nbsp;-&nbsp;';
		}
		$checkline .='<br /><br /><small>'.__('© by xili-tidy-tags.','xili_tidy_tags').'</small>';
			echo '<div style="margin:2px; padding:3px; border:1px solid #ccc;"><label>'.__('Tags groups','xili_tidy_tags').':<br /></label><br />'.$checkline.'</div>';	
	}
	
	/**
	 * a new term was created
	 *
	 * @since 0.8.0
	 * 
	 *
	 */
	function xili_created_term ($term_id, $tt_id) {
		/* check if it is a term from 'post_tag' */
		$term = get_term($term_id,'post_tag');
		if ($term) {
			$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
			$groupids = array();
			foreach ($listgroups as $group) {
				$idcheck = 'group-'.$group->term_id;
				if (isset($_POST[$idcheck])) {
					 	
						$groupids[]= (int) $group->term_id;
				}	
			}
			wp_set_object_terms($term_id, $groupids, TAXOTIDYTAGS,false);
		}	
	}	
	
	/**
	 * add admin menu and associated pages of admin UI tools page
	 *
	 * @since 0.8.0
	 * 
	 *
	 */
	function xili_add_pages() {
		 
		  	$this->thehook = add_object_page(__('Tags groups','xili_tidy_tags'), __('Tidy tags','xili_tidy_tags'), 8, __FILE__, array(&$this,'xili_tidy_tags_settings'));
		 	add_action('load-'.$this->thehook, array(&$this,'on_load_page'));
		 	/* sub-page */
		 	$this->thehook2 = add_submenu_page(__FILE__, __('Tidy tags','xili_tidy_tags'), __('Tidy tags assign','xili_tidy_tags'), 8, 'xili_tidy_tags_assign', array(&$this,'xili_tidy_tags_assign'));
		 	add_action('load-'.$this->thehook2, array(&$this,'on_load_page2'));
 	}
	
	function on_load_page() {
			wp_enqueue_script('common');
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('postbox');
			add_meta_box('xili_tidy_tags-sidebox-1', __('Message','xili_tidy_tags'), array(&$this,'on_sidebox_1_content'), $this->thehook , 'side', 'core');
			add_meta_box('xili_tidy_tags-sidebox-3', __('Info','xili_tidy_tags'), array(&$this,'on_sidebox_2_content'), $this->thehook , 'side', 'core');
	}
	
	function on_load_page2() {
			wp_enqueue_script('common');
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('postbox');
			add_meta_box('xili_tidy_tags-sidebox-1', __('Message','xili_tidy_tags'), array(&$this,'on_sidebox_1_content'), $this->thehook2 , 'side', 'core');
			add_meta_box('xili_tidy_tags-sidebox-3', __('Info','xili_tidy_tags'), array(&$this,'on_sidebox_2_content'), $this->thehook2 , 'side', 'core');
	}
	
	
	/**
	 * private functions for dictionary_settings
	 *
	 * @since 0.8.0
	 *
	 * fill the content of the boxes (right side and normal)
	 * 
	 */
	
	function  on_sidebox_1_content($data=array()) { 
		extract($data);
		?>
	 	<h4><?php _e('Note:','xili_tidy_tags') ?></h4>
		<p><?php echo $message;?></p>
		<?php
	}
	
	function  on_sidebox_2_content($data=array()) {  
	 	extract($data);
	 	if ($xili_tidy_tags_page == 'settings') { 
	 		echo '<p style="margin:2px; padding:3px; border:1px solid #ccc;">'.__('On this page, the tags groups are defined. The special groups for xili-language plugin are importable.<br /> In <b>beta</b> step, some technical infos are displayed in the tables or boxes.<br />','xili_tidy_tags').'</p>';
	 	} elseif ($xili_tidy_tags_page == 'assign') {	
	 		echo '<p style="margin:2px; padding:3px; border:1px solid #ccc;">'.__('On this page, in a oneshot way, it is possible to assign the tags to one or more groups defined on the other page of <i>xili-tidy-tags</i> plugin.','xili_tidy_tags').'</p>';	
	 	}	 ?>
		<p><?php _e('This 3rd plugin to test the new taxonomy… <b>xili-tidy-tags</b> is a tool for grouping tags by language or semantic group. Initially developped to enrich multilingual website powered by xili-language plugin.','xili_tidy_tags') ?></p>
		<?php
	}
	
	function  on_sidebox_3_content($data=array()) { 
	 	extract($data); 
	 	if (!defined('TAXONAME')) { ?>
	 		<p class="submit"><?php _e('xili-language plugin is not activated.','xili_tidy_tags') ?> </p>
	 		<?php
	 	} else {
	 		$res = is_term (LANGSTAGSGROUPNAME,TAXOTIDYTAGS);
		 	if ($res) {
		 		?>
				<p class="submit"><?php _e('The group of languages is set for use with xili-language plugin.','xili_tidy_tags') ?> </p> 
				<?php	
		 	} else {
		 		echo '<p class="submit">'.__('It is possible to import the group of languages.','xili_tidy_tags').'</p>';
		 		echo '<p class="submit"><input type="submit" name="reset" value="'.__('Import…','xili_tidy_tags').'" /></p>';	
		 	}
	 	}
	}
	
	function  on_normal_1_content($data=array()) {
	 	extract($data); ?>
		<?php /**/ ?>
					<table class="widefat">
						<thead>
						<tr>
						<th scope="col" style="text-align: center"><?php _e('ID','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Name','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Description','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Group slug','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Group taxo ID','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Parent','xili_tidy_tags') ?></th>
	        			<th scope="col" width="90" style="text-align: center"><?php _e('Tags') ?></th>
	        			<th colspan="2" style="text-align: center"><?php _e('Action') ?></th>
						</tr>
						</thead>
						<tbody id="the-list">
							<?php $this->xili_tags_group_row(); /* the lines */?>
						</tbody>
					</table>
					
		<?php
	}
	
	function on_normal_2_content($data=array()) { 
		extract($data); /* form to add or edit group */
		?>
		
		<h2 id="add" <?php if ($action=='delete') echo 'style="color:#FF1111;"'; ?>><?php _e($formtitle,'xili_tidy_tags') ?></h2>
		<?php if ($action=='edit' || $action=='delete') :?>
			<input type="hidden" name="tagsgroup_term_id" value="<?php echo $tagsgroup->term_id ?>" />
			<input type="hidden" name="tagsgroup_parent" value="<?php echo $tagsgroup->parent ?>" />
		<?php endif; ?>
		<table class="editform" width="100%" cellspacing="2" cellpadding="5">
			<tr>
				<th width="33%" scope="row" valign="top" align="right"><label for="tagsgroup_name"><?php _e('Name','xili_tidy_tags') ?></label>:&nbsp;</th>
				<td width="67%"><input name="tagsgroup_name" id="tagsgroup_name" type="text" value="<?php echo attribute_escape($tagsgroup->name); ?>" size="40" <?php if($action=='delete') echo 'disabled="disabled"' ?> /></td>
			</tr>
			<tr>
				<th scope="row" valign="top" align="right"><label for="tagsgroup_nicename"><?php _e('tags group slug','xili_tidy_tags') ?></label>:&nbsp;</th>
				<td><input name="tagsgroup_nicename" id="tagsgroup_nicename" type="text" value="<?php echo attribute_escape($tagsgroup->slug); ?>" size="40" <?php if($action=='delete') echo 'disabled="disabled"' ?> /></td>
			</tr>
			<tr>
				<th scope="row" valign="top" align="right"><label for="tagsgroup_description"><?php _e('Description','xili_tidy_tags') ?></label>:&nbsp;</th>
				<td><input name="tagsgroup_description" id="tagsgroup_description" size="40" value="<?php echo $tagsgroup->description; ?>" <?php if($action=='delete') echo 'disabled="disabled"' ?> /></td>
				
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top" align="right"><label for="tagsgroup_parent"><?php _e('kinship','xili_tidy_tags') ?></label> :&nbsp;</th>
				<td>
			  	<?php $this->xili_selectparent_row($tagsgroup->term_id,$tagsgroup); /* choice of parent line*/?>
             	</td>
			</tr>
			<tr>
			<th><p class="submit"><input type="submit" name="reset" value="<?php echo $cancel_text ?>" /></p></th>
			<td>
			<p class="submit"><input type="submit" name="submit" value="<?php echo $submit_text ?>" /></p>
			</td>
			</tr>
		</table>
	<?php
	}
	
	function xili_selectparent_row($term_id=0,$tagsgroup,$listby='name') {
		if ($term_id == 0) {
				$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
				?>
				<select name="tagsgroup_parent" id="tagsgroup_parent" style="width:100%;">
		  				<option value="no_parent" ><?php _e('no parent','xili_tidy_tags'); ?></option>
				<?php
				foreach ($listterms as $curterm) {
					if ($curterm->parent == 0)
						echo '<option value="'.$curterm->term_id.'" >'.$curterm->name.'</option>';
				} ?>
				</select>
				<br />
	    		<?php _e('Select the parent if necessary','xili_tidy_tags');
	     	} else {
	     	
	     		if ($tagsgroup->parent == 0) {
	     			$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'parent' => $term_id));
					// display childs
					if (!empty($listterms)) {
						echo __('parent of: ','xili_tidy_tags');
						echo '<ul>';
						foreach ($listterms as $curterm) { 
							echo '<li value="'.$curterm->term_id.'" >'.$curterm->name.'</li>';
						}
						echo '</ul>';
					} else {
						echo __('no child now','xili_tidy_tags')."<br /><br />";	
					}	
	     		} else {
	     			echo __('child of: ','xili_tidy_tags');
	     			$parent_term = get_term($tagsgroup->parent,TAXOTIDYTAGS,OBJECT,'edit');
	     			echo $parent_term->name; ?>
	     			<input type="hidden" name="tagsgroup_parent" value="<?php echo $parent_term->term_id ?>" />	
	     	<?php }	
			}	
	}
	
	/* Dashboard - Manage - Tidy tags */
	function xili_tidy_tags_settings() { 
		$xili_tidy_tags_page = 'settings';
		$formtitle = 'Add a group'; /* translated in form */
		$submit_text = __('Add &raquo;','xili_tidy_tags');
		$cancel_text = __('Cancel');
		if (isset($_POST['reset'])) {
			$action=$_POST['reset'];
		} elseif (isset($_POST['updateoptions'])) {
			$action='updateoptions';
		} elseif (isset($_POST['importxililanguages'])) {
			$action='importxililanguages';
		} elseif (isset($_POST['action'])) {
			$action=$_POST['action'];
		}
		
		if (isset($_GET['action'])) :
			$action=$_GET['action'];
			$term_id = $_GET['term_id'];
		endif;
		$message = $action ;
		switch($action) {
			case 'importxililanguages';
				$this->xili_langs_import_terms ();
				$actiontype = "add";
			    $message .= " - ".__('The languages groups was added.','xili_tidy_tags');
				break;
				
			case 'add';
				$term = $_POST['tagsgroup_name'];
				$args = array( 'alias_of' => '', 'description' => $_POST['tagsgroup_description'], 'parent' => (int) $_POST['tagsgroup_parent'], 'slug' => $_POST['tagsgroup_nicename']);
			    $theids = wp_insert_term( $term, TAXOTIDYTAGS, $args);
			    wp_set_object_terms($theids['term_id'], (int)$_POST['tagsgroup_parent'], TAXOTIDYTAGS);
				$actiontype = "add";
			    $message .= " - ".__('A new group was added.','xili_tidy_tags');
			     break;
			
			case 'edit';
				$actiontype = "edited";
			    $tagsgroup = get_term($term_id,TAXOTIDYTAGS,OBJECT,'edit');
			    $submit_text = __('Update &raquo;','xili_tidy_tags');
			    $formtitle =  'Edit Group';
			    $message .= " - ".__('Group to update.','xili_tidy_tags');
				break;
			
			case 'edited';
			    $actiontype = "add";
			    $term = $_POST['tagsgroup_term_id'];
				$args = array( 'alias_of' => '', 'description' => $_POST['tagsgroup_description'], 'parent' => $_POST['tagsgroup_parent'], 'slug' =>$_POST['tagsgroup_nicename']);
				$theids = wp_update_term( $term, TAXOTIDYTAGS, $args);	
				$message .= " - ".__('A group was updated.','xili_tidy_tags');
			    break;
				
			case 'delete';
			    $actiontype = "deleting";
			    $submit_text = __('Delete &raquo;','xili_tidy_tags');
			    $formtitle = 'Delete group';
			    $tagsgroup = get_term($term_id,TAXOTIDYTAGS,OBJECT,'edit');
			    $message .= " - ".__('A group to delete.','xili_tidy_tags');
			    break;
			    	
			case 'deleting';
			    $actiontype = "add";
			    $term = $_POST['tagsgroup_term_id'];
			    wp_delete_term( $term, TAXOTIDYTAGS, $args);
			    $message .= " - ".__('A group was deleted.','xili_tidy_tags');
			    break;
			     
			case 'reset';    
			    $actiontype = "add";
			    break;
			    
			default :
			    $actiontype = "add";
			    $message .= __('Find the list of groups.','xili_tidy_tags');
		}	
		
		/* register the main boxes always available */
		add_meta_box('xili_tidy_tags-sidebox-2', __('Actions','xili_tidy_tags'), array(&$this,'on_sidebox_3_content'), $this->thehook , 'side', 'core'); /* Actions */ 
		add_meta_box('xili_tidy_tags-normal-1', __('Groups of Tags','xili_tidy_tags'), array(&$this,'on_normal_1_content'), $this->thehook , 'normal', 'core'); /* list of groups*/
		add_meta_box('xili_tidy_tags-normal-2', __('The group','xili_tidy_tags'), array(&$this,'on_normal_2_content'), $this->thehook , 'normal', 'core'); /* the group*/
		
		/* form datas in array for do_meta_boxes() */
		$data = array('xili_tidy_tags_page' => $xili_tidy_tags_page,'message'=>$message,'messagepost'=>$messagepost,'action'=>$action, 'formtitle'=>$formtitle, 'tagsgroup'=>$tagsgroup,'submit_text'=>$submit_text,'cancel_text'=>$cancel_text, 'formhow'=>$formhow, 'orderby'=>$orderby,'term_id'=>$term_id);
		?>
		<div id="xili-tidy-tags-settings" class="wrap">
			<?php screen_icon('tools'); ?>
			<h2><?php _e('Tidy tags groups','xili_tidy_tags') ?></h2>
			<form name="add" id="add" method="post" action="admin.php?page=xili-tidy-tags/xili-tidy-tags.php">
				<input type="hidden" name="action" value="<?php echo $actiontype ?>" />
				<?php wp_nonce_field('xili-tidy-tags-settings'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
		
				<div id="poststuff" class="metabox-holder">
					<div id="side-info-column" class="inner-sidebar">
						<?php do_meta_boxes($this->thehook, 'side', $data); ?>
					</div>
					<div id="post-body" class="has-sidebar">
						<div id="post-body-content" class="has-sidebar-content">
					
	   					<?php do_meta_boxes($this->thehook, 'normal', $data); ?>
						</div>
						 	
					<h4>xili-tidy-tags - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php _e('Author'); ?>" >xiligroup.com</a>™ - msc 2009 - v. <?php echo XILITIDYTAGS_VER; ?></h4>
							
					</div>
				</div>
		</form>
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->thehook; ?>');
			});
			//]]>
		</script> 
		
	<?php
	}
	/*
	 * Import the terms (languages) set by xili-language	 *
	 *
	 */
	function xili_langs_import_terms () {
		$term = LANGSTAGSGROUPNAME;
		$args = array( 'alias_of' => '', 'description' => 'default lang group', 'parent' => 0, 'slug' =>LANGSTAGSGROUPSLUG);
		$resgroup = wp_insert_term( $term, TAXOTIDYTAGS, $args);
		
		$listlanguages = get_terms(TAXONAME, array('hide_empty' => false));
		foreach ($listlanguages as $language) {
			$args = array( 'alias_of' => '', 'description' => $language->description, 'parent' => $resgroup['term_id']);						$res = wp_insert_term($language->name, TAXOTIDYTAGS, $args);
		}
	}
	
	/*
	 * Display the rows of group of tags
	 *
	 *
	 */
	function xili_tags_group_row() {
		$listtagsgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		if (empty($listtagsgroups)) {
			/* import */
			if (defined('TAXONAME')) { /* xili-language is present */
				$this->xili_langs_import_terms ();
			} else {
				/*create a default line with the default group*/
				$term = 'tidy group';
				$args = array( 'alias_of' => '', 'description' => 'default xili tidy tags group', 'parent' => 0);
				$resgroup = wp_insert_term( $term, TAXOTIDYTAGS, $args);
			}	
			$listtagsgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));	
		}
		foreach ($listtagsgroups as $tagsgroup) {
			$class = ((defined('DOING_AJAX') && DOING_AJAX) || " class='alternate'" == $class ) ? '' : " class='alternate'";
	
			$tagsgroup->count = number_format_i18n( $tagsgroup->count );
			$posts_count = ( $tagsgroup->count > 0 ) ? "<a href='edit.php?lang=$tagsgroup->term_id'>$tagsgroup->count</a>" : $tagsgroup->count;	
		
			$edit = "<a href='?page=xili-tidy-tags/xili-tidy-tags.php&amp;action=edit&amp;term_id=".$tagsgroup->term_id."' >".__( 'Edit' )."</a></td>";	
			/* delete link &amp;action=edit&amp;term_id=".$tagsgroup->term_id."*/
			$edit .= "<td><a href='?page=xili-tidy-tags/xili-tidy-tags.php&amp;action=delete&amp;term_id=".$tagsgroup->term_id."' class='delete'>".__( 'Delete' )."</a>";	
			
			$line="<tr id='cat-$tagsgroup->term_id'$class>
			<th scope='row' style='text-align: center'>$tagsgroup->term_id</th>
			<td> ";
			$tabb = ($tagsgroup->parent != 0) ? " >" : "" ;
			$line .= "$tabb $tagsgroup->name</td>
			<td>$tagsgroup->description</td>
			<td>$tagsgroup->slug</td>
			<td>$tagsgroup->term_taxonomy_id</td>
			<td>$tagsgroup->parent</td>
			<td align='center'>$tagsgroup->count</td> 
			<td>$edit</td>\n\t</tr>\n"; /*to complete*/
			echo $line;
		}
				
	}
	
	function on_sub_normal_1_content ($data=array()) {
	 	//extract($data); ?>
		<?php /**/ ?>
					<table class="widefat">
						<thead>
						<tr>
						<th scope="col" style="text-align: center"><?php _e('ID','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Name','xili_tidy_tags') ?></th>
	        			<th scope="col" width="90" style="text-align: center"><?php _e('Posts') ?></th>
	        			<th colspan="2" style="text-align: center"><?php _e('Action') ?></th>
						</tr>
						</thead>
						<tbody id="the-list">
							<?php $this->xili_tags_row(); /* the lines */?>
						</tbody>
					</table>
					
		<?php
		
	}
	
	function  on_sub_sidebox_3_content($data=array()) { 
	 	extract($data);?>
	 	<p><?php _e('After checking or unchecking do not forget to click update button !','xili_tidy_tags'); ?></p>
		<p class="submit"><input type="submit" name="reset" value="<?php echo $cancel_text ?>" /></p>
			
		<p class="submit"><input type="submit" class="button-primary" id="update" name="update" value="<?php echo $submit_text ?>" /></p>
		<div style="margin:2px; padding:3px; border:1px solid #ccc;">
		<?php
		$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
				?>
				<select name="tagsgroup_parent_select" id="tagsgroup_parent_select" style="width:100%;">
		  				<option value="no_select" ><?php _e('No sub-selection','xili_tidy_tags'); ?></option>
				<?php
				foreach ($listterms as $curterm) {
					if ($curterm->parent == 0) {
						$checked = ($this->subselect == $curterm->term_id) ? 'selected="selected"' :'' ;
						echo '<option value="'.$curterm->term_id.'" '.$checked.' >'.$curterm->name.'</option>';
					}
				} ?>
				</select>
				<br /> <p class="submit"><input type="submit" id="subselection" name="subselection" value="<?php _e('Sub select…','xili_tidy_tags'); ?>" /></p></div><?
	}
	/**
	 * The rows of the tags and checkboxes to assign group(s)
	 *
	 * @since 0.8.0
	 *
	 * @uses 
	 * @param 
	 * @return the rows for admin ui
	 */
	function xili_tags_row() {
		//global $wpdb;
		$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		$hiddenline = array ();
		$edit =''; $i=0;
		$listgroupids= array();
		$subselectgroups = array();
		if ($this->subselect > 0) {
			$subselectgroups[] = $this->subselect; /* the parent group and */
			/*childs of */
			$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'parent' => $this->subselect));
			if (!empty($listterms)) {
					foreach ($listterms as $curterm) { 
							$subselectgroups[] = $curterm->term_id;
					}
			}		
		}	
		foreach ($listgroups as $group) {
			$listgroupids[] = $group->term_id;
			if (empty($subselectgroups)) { /* groups in -- visibles => à changer vide ou isset*/	
				$editformat .= '<input type="checkbox" id="line-%1$s-'.$group->term_id.'" name="line-%1$s-'.$group->term_id.'" value="'.$group->term_id.'" checked'.$group->term_id.' />'.$group->name.'&nbsp;&nbsp;';	
			} else { 
				 if (in_array ($group->term_id,$subselectgroups)) {
				 	$editformat .= '<input type="checkbox" id="line-%1$s-'.$group->term_id.'" name="line-%1$s-'.$group->term_id.'" value="'.$group->term_id.'" checked'.$group->term_id.' />'.$group->name.'&nbsp;&nbsp;';
				 } else {
				 	$hiddenline[] = $group->term_id ;	
				 }
			}	
		}
		
		$listtags = get_terms('post_tag', array('hide_empty' => false,'get'=>'all'));
		foreach ($listtags as $tag) {
			$class = ((defined('DOING_AJAX') && DOING_AJAX) || " class='alternate'" == $class ) ? '' : " class='alternate'";
	
			$tag->count = number_format_i18n( $tag->count );
			$posts_count = ( $tag->count > 0 ) ? "<a href='edit.php?tag=$tag->name'>$tag->count</a>" : $tag->count;	
			
			$edit = sprintf($editformat,$tag->term_id);
			$hiddenlines = "";
			foreach ($listgroupids as $groupid) {
				if (is_object_in_term($tag->term_id,TAXOTIDYTAGS,$groupid)) {
					$edit = str_replace('checked'.$groupid,'checked="checked"',$edit);
					if (in_array($groupid,$hiddenline))
						$hiddenlines .= '<input type="hidden" name="line-'.$tag->term_id.'-'.$groupid.'" value="'.$tag->term_id.'" />';
					
				} else {
					$edit = str_replace('checked'.$groupid,'',$edit);
				}
				
			}
								
			$line="<tr id='cat-$tag->term_id'$class>
			<th scope='row' style='text-align: center'>$tag->term_id</th>
			<td> <a href='edit-tags.php?action=edit&tag_ID=".$tag->term_id."'>".$tag->name."</a> </td>
			<td align='center'>$posts_count</td>
			 
			<td>$edit\n$hiddenlines</td>\n\t</tr>\n"; /*to complete*/
			echo $line;
		}
	}
		
	/* page for tags assign to (a) group(s) */
	function xili_tidy_tags_assign () { 
		$xili_tidy_tags_page = 'assign';
		$submit_text = __('Update','xili_tidy_tags');
		$cancel_text = __('Cancel');
		
		if (isset($_POST['update'])) {
			$action='update';
		}
		$subselectgroups = array();
		if(isset($_POST['tagsgroup_parent_select']) && $_POST['tagsgroup_parent_select'] != 'no_select') {
				$this->subselect = (int) $_POST['tagsgroup_parent_select'];
			} else {
				$this->subselect = 0;
			}	
		if (isset($_POST['subselection'])) {
			$action='subselection';
		}
		if (isset($_GET['action'])) :
			$action = $_GET['action'];
			$term_id = $_GET['term_id'];
		endif;
		$message = $action ;
		switch($action) {
			
			case 'subselection';
				$message .= ' selection of '.$_POST['tagsgroup_parent_select'];
				$actiontype = "add";
				break;
							
			case 'update';
				$message .= 'ok ';
				$message .= $this->checkboxes_update_them();
				$actiontype = "add";
				break;
				
			case 'reset';    
			    $actiontype = "add";
			    break;
			    
			default :
			    $actiontype = "add";
			    $message .= __('Find the list of tags.','xili_tidy_tags');	
		}
		/* form datas in array for do_meta_boxes() */
		$data = array('xili_tidy_tags_page' => $xili_tidy_tags_page,'message'=>$message,'messagepost'=>$messagepost,'action'=>$action,'submit_text'=>$submit_text,'cancel_text'=>$cancel_text,'term_id'=>$term_id);	
			
		/* register the main boxes always available */
		add_meta_box('xili_tidy_tags-sidebox-2', __('Actions','xili_tidy_tags'), array(&$this,'on_sub_sidebox_3_content'), $this->thehook2 , 'side', 'core'); /* Actions */ 
		add_meta_box('xili_tidy_tags-normal-1', __('Tidy Tags','xili_tidy_tags'), array(&$this,'on_sub_normal_1_content'), $this->thehook2 , 'normal', 'core'); /* list of tags*/
			
			?>
		<div id="xili-tidy-tags-assign" class="wrap">
			<?php screen_icon('post'); ?>
			<h2><?php _e('Tags in group','xili_tidy_tags') ?></h2>
			<form name="add" id="add" method="post" action="admin.php?page=xili_tidy_tags_assign">
				<input type="hidden" name="action" value="<?php echo $actiontype ?>" />
				<?php wp_nonce_field('xili-tidy-tags-settings'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); ?>
		
				<div id="poststuff" class="metabox-holder">
					<div id="side-info-column" class="inner-sidebar">
						<?php do_meta_boxes($this->thehook2, 'side', $data); ?>
					</div>
					<div id="post-body" class="has-sidebar">
						<div id="post-body-content" class="has-sidebar-content">
					
	   					<?php do_meta_boxes($this->thehook2, 'normal', $data); ?>
						</div>
						 	
					<h4>xili-tidy-tags - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php _e('Author'); ?>" >xiligroup.com</a>™ - msc 2009 - v. <?php echo XILITIDYTAGS_VER; ?></h4>
							
					</div>
				</div>
		</form>
		</div>
		<script type="text/javascript">
			//<![CDATA[
			jQuery(document).ready( function($) {
				// close postboxes that should be closed
				$('.if-js-closed').removeClass('if-js-closed').addClass('closed');
				// postboxes setup
				postboxes.add_postbox_toggles('<?php echo $this->thehook2; ?>');
			});
			//]]>
		</script>
		<?	
	}	
	/*
	 * Update the relationships according CheckBoxes array
	 *
	 */
	function checkboxes_update_them() {
	
		$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		$listtags = get_terms('post_tag', array('hide_empty' => false,'get'=>'all'));
		foreach ($listtags as $tag) {
			$groupids = array();
			foreach ($listgroups as $group){
				$idcheck = 'line-'.$tag->term_id.'-'.$group->term_id;
				//$hiddencheck = 'hidd-'.$tag->term_id.'-'.$group->term_id;
				/*if (isset($_POST[$hiddencheck])) {
					if (!isset($_POST[$idcheck])) {
						//$box2reset[$group->term_id][]=$tag->term_id;
					} else {
						$groupids[]= (int) $group->term_id;
					}	
				} else {*/
					if (isset($_POST[$idcheck])) {
						//$box2update[$group->term_id][]=$tag->term_id;
						$groupids[]= (int) $group->term_id;
					}
				//}
				
			}
			wp_set_object_terms((int) $tag->term_id, $groupids, TAXOTIDYTAGS,false);
		}
		
		return ;//$box2update;
	}
	
} /* end class */

/**
 * Display tidy tag cloud. (adapted form wp_tag_cloud - category-template)
 *
 * The text size is set by the 'smallest' and 'largest' arguments, which will
 * use the 'unit' argument value for the CSS text size unit. The 'format'
 * argument can be 'flat' (default), 'list', or 'array'. The flat value for the
 * 'format' argument will separate tags with spaces. The list value for the
 * 'format' argument will format the tags in a UL HTML list. The array value for
 * the 'format' argument will return in PHP array type format.
 *
 * The 'orderby' argument will accept 'name' or 'count' and defaults to 'name'.
 * The 'order' is the direction to sort, defaults to 'ASC' and can be 'DESC'.
 *
 * The 'number' argument is how many tags to return. By default, the limit will
 * be to return the top 45 tags in the tag cloud list.
 *
 * The 'topic_count_text_callback' argument is a function, which, given the count
 * of the posts  with that tag, returns a text for the tooltip of the tag link.
 *
 * The 'exclude' and 'include' arguments are used for the {@link get_tags()}
 * function. Only one should be used, because only one will be used and the
 * other ignored, if they are both set.
 *
 * @since 0.8.0
 * @updated 0.8.2
 *
 * @param array|string $args Optional. Override default arguments.
 * @return array Generated tag cloud, only if no failures and 'array' is set for the 'format' argument.
 */
function xili_tidy_tag_cloud( $args = '' ) {
	if ( is_array($args) )
		$r = &$args;
	else
		parse_str($args, $r); 
	$defaults = array(
		'smallest' => 8, 'largest' => 22, 'unit' => 'pt', 'number' => 45,
		'format' => 'flat', 'orderby' => 'name', 'order' => 'ASC',
		'exclude' => '', 'include' => '', 'link' => 'view', 'tagsgroup' => '', 'tagsallgroup' => ''
	);
	$r = array_merge($defaults, $r);
	extract($r); /* above changed because new args */
	if (($tagsgroup == '' && $tagsallgroup == '' ) || !function_exists('get_terms_of_groups')) {
		$tags = get_tags( array_merge( $r, array( 'orderby' => 'count', 'order' => 'DESC' ) ) ); // Always query top tags
	} else {
		if ($tagsgroup !='') {
			$groupterm = is_term($tagsgroup,TAXOTIDYTAGS);
			$group_id[] = $groupterm['term_id'];
		}
		if ($tagsallgroup !='') {
			$groupterm = is_term($tagsallgroup,TAXOTIDYTAGS);
			$group_id[] = $groupterm['term_id'];
		}
		//$tags = get_terms_with_order ($group_id, TAXOTIDYTAGS,'post_tag'); /* the two taxonomies (both of terms) */
		$tags = get_terms_of_groups ($group_id, TAXOTIDYTAGS,'post_tag');
	}	

	if ( empty( $tags ) )
		return;

	foreach ( $tags as $key => $tag ) {
		if ( 'edit' == $args['link'] )
			$link = get_edit_tag_link( $tag->term_id );
		else
			$link = get_tag_link( $tag->term_id );
		if ( is_wp_error( $link ) )
			return false;

		$tags[ $key ]->link = $link;
		$tags[ $key ]->id = $tag->term_id;
	}

	$return = wp_generate_tag_cloud( $tags, $args ); // Here's where those top tags get sorted according to $args

	$return = apply_filters( 'wp_tag_cloud', $return, $args );

	if ( 'array' == $args['format'] )
		return $return;

	echo $return;
}

/**** Functions that improve taxinomy.php ****/

/**
 * get terms and add order in term's series that are in a taxonomy 
 * (not in class for general use
 *
 * @since 0.9.8.2 - provided here if xili-language plugin is not used
 *
 */
if (!function_exists('get_terms_of_groups')) { 
	function get_terms_of_groups ($group_ids, $taxonomy, $taxonomy_child, $order = '') {
		global $wpdb;
		if ( !is_array($group_ids) )
			$group_ids = array($group_ids);
		$group_ids = array_map('intval', $group_ids);
		$group_ids = implode(', ', $group_ids);
		$orderby = '';
		if ($order == 'ASC' || $order == 'DESC') $orderby = 'ORDER BY tr.term_order '.$order ;
		$query = "SELECT t.*, tt2.term_taxonomy_id, tt2.description,tt2.parent, tt2.count, tt2.taxonomy, tr.term_order FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN $wpdb->terms AS t ON t.term_id = tr.object_id INNER JOIN $wpdb->term_taxonomy AS tt2 ON tt2.term_id = tr.object_id WHERE tt.taxonomy IN ('".$taxonomy."') AND tt2.taxonomy = '".$taxonomy_child."' AND tt.term_id IN (".$group_ids.") ".$orderby;
		$listterms = $wpdb->get_results($query);
		if ( ! $listterms )
			return array();

		return $listterms;
	}
}

/*
 *
 * @ 0.9.0 forcasted - use only for tests 
 *
 */
class xili_tidy_tags_cloud_widget {

	function xili_tidy_tags_cloud_widget () {
		add_action('widgets_init', array(&$this, 'init_widget'));
	}

	function init_widget() {
		if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
			return;
		register_sidebar_widget(array('xili_tidy_tags_cloud_widget','widgets'),array(&$this, 'widget'));
		register_widget_control(array('xili_tidy_tags_cloud_widget', 'widgets'), array(&$this, 'widget_options'));
	}

	function widget($args) {
		global $wpdb;

		$options = get_option('xili_tidy_tags_cloud_widget_options');
		extract($args);
		$cloudsargs = array();
		echo $before_widget.$before_title.__($options['title'],THEME_TEXTDOMAIN).$after_title;
		if ('the_curlang' == $options['tagsgroup']) {
			$cloudsargs[] = 'tagsgroup='.the_curlang();
		} elseif ('the_category' == $options['tagsgroup'])  {	
			$cloudsargs[] = 'tagsgroup='.single_cat_title('',false);
		} else {
			$cloudsargs[] = 'tagsgroup='.$options['tagsgroup'];
		}
		
		$cloudsargs[] = 'tagsallgroup='.$options['tagsallgroup'];
		
		if (function_exists('xili_tidy_tag_cloud')) { 
			echo '<div class="xilitidytagscloud">';
				xili_tidy_tag_cloud(implode("&",$cloudsargs));
			echo '</div>';
			}
		echo $after_widget;
	}

	function widget_options() {
		if (isset($_POST['xili_tidy_tags_widget_submit'])) {
			$options['title'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_title"]));
			$options['tagsgroup'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_tagsgroup"]));
			$options['tagsallgroup'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_tagsallgroup"]));
			
			//
			update_option('xili_tidy_tags_cloud_widget_options',$options);
		}
		$options=get_option('xili_tidy_tags_cloud_widget_options');
		echo '<label for="xili_tdtc_widget_title">'.__('Title').': <input id="xili_tdtc_widget_title" name="xili_tdtc_widget_title" type="text" value="'.attribute_escape($options['title']).'" /></label>';
		// other options min max number group 1 and 2 tagsallgroup
		echo '<label for="xili_tdtc_widget_tagsgroup">'.__('Groups').': <input id="xili_tdtc_widget_tagsgroup" name="xili_tdtc_widget_tagsgroup" type="text" value="'.attribute_escape($options['tagsgroup']).'" /></label>';
		echo '<label for="xili_tdtc_widget_tagsallgroup">'.__('Parent group').': <input id="xili_tdtc_widget_tagsallgroup" name="xili_tdtc_widget_tagsallgroup" type="text" value="'.attribute_escape($options['tagsallgroup']).'" /></label>';
		
		//
		echo '<input type="hidden" id="xili_tidy_tags_widget_submit" name="xili_tidy_tags_widget_submit" value="1" />';
	}
}


/**
 * instantiation of xili_tidy_tags class
 *
 * @since 0.8.0
 *
 * @param metabox (for other posts in post edit UI - beta tests)
 * @param ajax ( true if ajax is activated for post edit admin UI - alpha tests )
 */
 
$xili_tidy_tags = new xili_tidy_tags (false, false);
 
// un-comment to test widget planned for 0.9.0
// $xili_tidy_tags_cloud_widget = new xili_tidy_tags_cloud_widget ();





?>