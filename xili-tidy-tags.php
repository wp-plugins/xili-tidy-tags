<?php
/*
Plugin Name: xili-tidy-tags
Plugin URI: http://dev.xiligroup.com/xili-tidy-tags/
Description: xili-tidy-tags is a tool for grouping tags by language or semantic group. Initially developed to enrich xili-language plugin and usable in all sites (CMS).
Author: dev.xiligroup.com - MS
Version: 1.2
Author URI: http://dev.xiligroup.com
*/
# 1.2 - 091122 - fix subselection sort in get_terms_of_groups (thanks to zarban)
# 1.1 - 091012 - new xili_the_tags() for the loop
# 1.0.1 - 090718 - new icon in admin menu - some fixes in php code for some servers (Thanks to Giannis)
# 1.0   - 090611 - add shortcode to include a cloud of a group of tags inside a post - compatible with WP 2.8
# 0.9.6 - 090602 <- # 0.8.1 - 090331 - see history in readme.txt -
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

define('XILITIDYTAGS_VER','1.2'); /* used in admin UI */

class xili_tidy_tags {
	
	var $is_metabox = false; /* for tests of special box in post */
	var $is_post_ajax = false; /* for tests using ajax in UI */
	var $langgroupid = 0; /* group of langs*/

	function xili_tidy_tags($metabox = false, $post_ajax = false) {
		$this->is_metabox = $metabox;
		$this->is_post_ajax = $post_ajax;
		
		/* activated when first activation of plug or automatic upgrade */
		register_activation_hook(__FILE__,array(&$this,'xili_tidy_tags_activate'));
	
		/*get current settings - name of taxonomy - name of query-tag - 0.9.8 new taxonomy taxolangsgroup */
		$this->xili_tidy_tags_activate();
		if ($this->xili_settings['version'] != '0.5') { /* updating value by default 0.9.5 */
			$this->xili_settings['version'] = '0.5';
			update_option('xili_tidy_tags_settings', $this->xili_settings);
		}		
			
		define('TAXOTIDYTAGS',$this->xili_settings['taxonomy']);
		define('LANGSTAGSGROUPSLUG',$this->xili_settings['tidylangsgroup']);
		define('LANGSTAGSGROUPNAME',$this->xili_settings['tidylangsgroupname']);
		$res = is_term (LANGSTAGSGROUPNAME,TAXOTIDYTAGS);
		if ($res) $this->langgroupid = $res ['term_id'];
		
		/* add new taxonomy in available taxonomies */
		register_taxonomy( TAXOTIDYTAGS, 'term',array('hierarchical' => true, 'update_count_callback' => ''));
		
		/* since 0.9.5 new default caps for admnistrator */
		if (is_admin()) {
			$wp_roles = new WP_Roles();  /* here because not present before */
			$role = $wp_roles->get_role('administrator');
			if (!$role->has_cap( 'xili_tidy_editor_set' )) $role->add_cap('xili_tidy_editor_set');
			if (!$role->has_cap( 'xili_tidy_editor_group' )) $role->add_cap('xili_tidy_editor_group'); 
		}
		
		/* hooks */	
		/* admin settings UI*/
		add_action('init', array(&$this, 'init_plugin')); /* text domain and caps of admin*/
		add_filter('plugin_action_links',  array(&$this,'xili_filter_plugin_actions'), 10, 2);
		
		add_action('admin_menu', array(&$this,'xili_add_pages'));
		
		add_action('add_tag_form', array(&$this,'xili_add_tag_form')); /* to choose a group for a new tag */
		add_action('edit_tag_form', array(&$this,'xili_edit_tag_form'));
		
		add_action('created_term', array(&$this,'xili_created_term'),10,2); /* a new term was created */
		add_action('edited_term', array(&$this,'xili_created_term'),10,2);
		
		/* actions for post and page admin UI */
		add_action('save_post', array(&$this,'xili_tags_grouping'),50); /* to affect tags to lang of saved post */
		
	}
	
	function xili_tidy_tags_activate() {
		/* admin has ever tidy roles */
		$this->xili_settings = get_option('xili_tidy_tags_settings');
		if (empty($this->xili_settings )) {
			$this->xili_settings = array(
			    'taxonomy'			=> 'xili_tidy_tags',
			    'tidylangsgroup'	=> 'tidy-languages-group',
			    'tidylangsgroupname'	=> 'All lang.',
			    'version' 			=> '0.5'
		    );
			update_option('xili_tidy_tags_settings', $this->xili_settings);			
		}	
	}	
	function init_plugin() {
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
			$settings_link = '<a href="admin.php?page=xili_tidy_tags_settings">' . __('Settings') . '</a>';
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
	 * in post edit UI if new term was created - give it a group 
	 *
	 * @since 0.9.4
	 * 
	 *
	 */
	function xili_tags_grouping ($post_ID) {
		if (!class_exists('xili_language')) return ; /* only used if present */
			$list_tags = wp_get_object_terms($post_ID, 'post_tag');
			if ( !$list_tags )
				return ; /* no tag*/
			$post_curlang = get_cur_language($post_ID);
			//$res = is_term (LANGSTAGSGROUPNAME,TAXOTIDYTAGS);
			//if ($res) $langgroupid = $res ['term_id'];
			$listlanggroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'parent' => $this->langgroupid));
			if ($listlanggroups) {
				foreach ($listlanggroups as $curgroup) { 
					$langsgroup[] = $curgroup->term_id;
				}
				$langsgroup[] = $this->langgroupid; /* add group parent */
				foreach ( $list_tags as $tag ) { /* test if the tag is owned by a group */ 
					$nbchecked = false;
					foreach ($langsgroup as $onelanggroup) {
						if (is_object_in_term($tag->term_id,TAXOTIDYTAGS,$onelanggroup)) {
							$nbchecked = true ;
						}
					}
					if ($nbchecked == false) { 
						if ($post_curlang == false) { /* add to group parent */
						    wp_set_object_terms((int) $tag->term_id, (int) $this->langgroupid, TAXOTIDYTAGS,false);
						} else {
							$res = is_term ($post_curlang,TAXOTIDYTAGS);
							wp_set_object_terms((int) $tag->term_id, (int) $res ['term_id'], TAXOTIDYTAGS,false);
						}
					}
				}
			}
	}
	
	/**
	 * add admin menu and associated pages of admin UI tools page
	 *
	 * @since 0.8.0
	 * @updated 0.9.5 - menu without repeat of main title, levels with new caps set by plugin array(&$this,'top_tidy_menu_title')
	 * @updated 1.0.1 - favicon.ico for menu title
	 */
	function xili_add_pages() {
		 
		  	$this->thehook0 = add_object_page(__('Tags groups','xili_tidy_tags'), __('Tidy Tags','xili_tidy_tags'), -1, __FILE__,'', WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/xili-favicon.ico');		  	
		  	$this->thehook = add_submenu_page(__FILE__, __('Tags groups','xili_tidy_tags'),__('Tidy tags settings','xili_tidy_tags'), 'xili_tidy_editor_set', 'xili_tidy_tags_settings', array(&$this,'xili_tidy_tags_settings'));
		  	add_action('load-'.$this->thehook, array(&$this,'on_load_page'));
		 	/* sub-page */
		 	$this->thehook2 = add_submenu_page(__FILE__, __('Tidy tags','xili_tidy_tags'), __('Tidy tags assign','xili_tidy_tags'), 'xili_tidy_editor_group', 'xili_tidy_tags_assign', array(&$this,'xili_tidy_tags_assign'));
		 	add_action('load-'.$this->thehook2, array(&$this,'on_load_page2'));
 	}
 	function top_tidy_menu_title () {
 		echo 'lili';
 	}
	
	function on_load_page() {
			wp_enqueue_script('common');
			wp_enqueue_script('wp-lists');
			wp_enqueue_script('postbox');
			add_meta_box('xili_tidy_tags-sidebox-1', __('Message','xili_tidy_tags'), array(&$this,'on_sidebox_1_content'), $this->thehook , 'side', 'core');
			add_meta_box('xili_tidy_tags-sidebox-3', __('Info','xili_tidy_tags'), array(&$this,'on_sidebox_2_content'), $this->thehook , 'side', 'core');
			if (current_user_can( 'administrator'))
				add_meta_box('xili_tidy_tags-sidebox-4', __('Capabilities','xili_tidy_tags'), array(&$this,'on_sidebox_4_content'), $this->thehook , 'side', 'core');
			
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
	 		echo '<p style="margin:2px; padding:3px; border:1px solid #ccc;">'.__('On this page, the tags groups are defined. The special groups for xili-language plugin are importable.<br /> For debug, some technical infos are displayed in the tables or boxes.<br />','xili_tidy_tags').'</p>';
	 	} elseif ($xili_tidy_tags_page == 'assign') {	
	 		echo '<p style="margin:2px; padding:3px; border:1px solid #ccc;">'.__('On this page, in a oneshot way, it is possible to assign the tags to one or more groups defined on the other page of <i>xili-tidy-tags</i> plugin.','xili_tidy_tags').'</p>';	
	 	}	 ?>
		<p><?php _e('This 3rd plugin to test the new taxonomy… <b>xili-tidy-tags</b> is a tool for grouping tags by language or semantic group. Initially developped to enrich multilingual website powered by xili-language plugin.','xili_tidy_tags') ?></p>
		<?php
	}
	/*
	 * Admin capabilities setting box
	 * @since 0.9.5
	 * Only visible if admin (cap : update_plugins)
	 */
	function  on_sidebox_4_content($data=array()) {
			global $wp_roles;
			$role = $wp_roles->get_role('editor');
			$editor_set = $role->has_cap ('xili_tidy_editor_set') ;
			$editor_group = $role->has_cap ('xili_tidy_editor_group') ;
	  		if ($editor_set && $editor_group)  
	  		{ 
	  			$selected3 = ' selected = "selected"'; 
	  		} elseif ( $editor_group ) {
	  			$selected2 = ' selected = "selected"';
			}
		  						
	 	?>
	 	<div style="margin:2px; padding:3px; border:1px solid #ccc;">
		<p><?php _e('Here, as admin, set capabilities of the editor:','xili_tidy_tags') ?></p>
		<select name="editor_caps" id="editor_caps" style="width:80%;">
  				<option value="no_caps" ><?php _e('no capability','xili_tidy_tags'); ?></option>
  				<option value="caps_grouping" <?php echo $selected2;?>><?php _e('Grouping','xili_tidy_tags');  ?></option>
  				<option value="caps_setting_grouping" <?php echo $selected3;?>><?php _e('Setting and grouping','xili_tidy_tags');?></option>
  		</select>
  		<?php
  		echo'<p class="submit"><input type="submit" name="editor_caps_submit" value="'.__('Set &raquo;','xili_tidy_tags').'" /></p></div>';	
	}
	
	/*
	 * Action's box
	 */
	function  on_sidebox_3_content($data=array()) { 
	 	extract($data);
	 	echo '<div style="margin:2px; padding:3px; border:1px solid #ccc;">';
	 	echo '<p>'.__('Add a tag\'s group for a chosen category','xili_tidy_tags').'</p>';
	 	/* build the selector of available categories */
	 	$categories = get_categories(array('get'=>'all')); /* even if cat is empty */
	 	echo '<select name="catsforgroup" id="catsforgroup" style="width:100%;">';
		  				echo '<option value="no" >'.__('choose a category','xili_tidy_tags').'</option>';
	 	foreach ($categories as $cat) {
	 		$catinside = is_term ($cat->slug,TAXOTIDYTAGS);
			if ($catinside == 0 && $cat->term_id != 1)
				echo '<option value="'.$cat->term_id.'" >'.$cat->name.'</option>';
		}
	 	echo '</select>';
	 	echo '<p>'.__('Choose a parent tag\'s group','xili_tidy_tags').'</p>';
	 	$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
		?>
		<select name="tags_parent" id="tags_parent" style="width:100%;">
  				<option value="no_parent" ><?php _e('no parent','xili_tidy_tags'); ?></option>
		<?php
		//$res = is_term (LANGSTAGSGROUPNAME,TAXOTIDYTAGS);
		//if ($res) $langgroupid = $res ['term_id'];
		foreach ($listterms as $curterm) {
			if ($curterm->parent == 0 && $curterm->term_id != $this->langgroupid)
				echo '<option value="'.$curterm->term_id.'" >'.$curterm->name.'</option>';
		} ?>
		</select>
		<br />
	    <?php
	 	echo '<p class="submit"><input type="submit" name="importacat" value="'.__('Add &raquo;','xili_tidy_tags').'" /></p>';
	 	echo '<p>'.__('See docs to set xili_tidy_tag_cloud function or widget to implement in theme…','xili_tidy_tags').'</p>';
	 	echo '</div>';
	 	echo '<div style="margin:2px; padding:3px; border:1px solid #ccc;">'; 
	 	if (!defined('TAXONAME')) { ?>
	 		<p class="submit"><?php _e('xili-language plugin is not activated.','xili_tidy_tags') ?> </p>
	 		<?php
	 	} else {
	 		$res = is_term (LANGSTAGSGROUPNAME,TAXOTIDYTAGS);
	 		if ($res) $childterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'parent' => $res['term_id']));
	 		if ($res && !empty($childterms)) {
		 		?>
				<p><?php _e('The group of languages is set for use with xili-language plugin.','xili_tidy_tags') ?> </p> 
				<?php	
		 	} else { /* since 0.9.5 */
		 		if (current_user_can( 'administrator')) {
			 		$countt = wp_count_terms(TAXONAME); /* count a minima one language */
			 		if ( $countt > 0 ) { 
			 			echo '<p class="submit">'.__('It is possible to import the group of languages.','xili_tidy_tags').'</p>';
			 			echo '<p class="submit"><input type="submit" name="importxililanguages" value="'.__('Import…','xili_tidy_tags').'" /></p>';
			 		} else {
			 			echo '<p class="submit">'.__('Go to settings of xili-language plugin and add languages','xili_tidy_tags').'</p>';
			 		}
		 		} else {
		 			echo '<p class="submit">'.__('See administrator for language settings.','xili_tidy_tags').'</p>';
		 		}	
		 	}
	 	}
	 	echo '</div>';
	 	
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
		
		<h2 id="addgroup" <?php if ($action=='delete') echo 'style="color:#FF1111;"'; ?>><?php _e($formtitle,'xili_tidy_tags') ?></h2>
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
			  	<?php $this->xili_selectparent_row($tagsgroup->term_id,$tagsgroup,$action); /* choice of parent line*/?>
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
	
	function xili_selectparent_row($term_id=0,$tagsgroup,$action) {
		if ($term_id == 0) {
				$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
				?>
				<select name="tagsgroup_parent" id="tagsgroup_parent" style="width:100%;">
		  				<option value="no_parent" ><?php _e('no parent','xili_tidy_tags'); ?></option>
				<?php
				foreach ($listterms as $curterm) {
					if ($curterm->parent == 0) {
						if (current_user_can( 'administrator')) {
							$possible = true;
						} elseif  ($curterm->term_id == $this->langgroupid) {
							$possible = false;
						} else {
							$possible = true;
						}		
						if ($possible)
							echo '<option value="'.$curterm->term_id.'" >'.$curterm->name.'</option>';
					}
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
					/* if modify*/
					if($action=='edit') {
						$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
				?>
				<select name="tagsgroup_parent" id="tagsgroup_parent" style="width:100%;">
		  				<option value="no_parent" ><?php _e('no parent','xili_tidy_tags'); ?></option>
				<?php
					foreach ($listterms as $curterm) {
						if ($curterm->parent == 0 && $curterm->term_id != $term_id)
							echo '<option value="'.$curterm->term_id.'" >'.$curterm->name.'</option>';
					} ?>
				</select>
				<br />
	    		<?php _e('Select the parent if necessary','xili_tidy_tags');
						
					}	
						
	     		} else {
	     			/* if modify*/
	     			$parent_term = get_term($tagsgroup->parent,TAXOTIDYTAGS,OBJECT,'edit');
	     			if($action=='delete') {
	     				echo __('child of: ','xili_tidy_tags');
	     				echo $parent_term->name; ?>
	     					<input type="hidden" name="tagsgroup_parent" value="<?php echo $parent_term->term_id ?>" />	
	     		<?php } else {
	     					$listterms = get_terms(TAXOTIDYTAGS, array('hide_empty' => false));
	     			?>
							<select name="tagsgroup_parent" id="tagsgroup_parent" style="width:100%;">
		  					<option value="no_parent" ><?php _e('no parent','xili_tidy_tags'); ?></option>
				<?php
							foreach ($listterms as $curterm) {
								if ($curterm->parent == 0 && $curterm->term_id != $term_id) {
									$checked = ($parent_term->term_id == $curterm->term_id) ? 'selected="selected"' :'' ;
									echo '<option value="'.$curterm->term_id.'" '.$checked.' >'.$curterm->name.'</option>';
								}
							} ?>
							</select>
				<br />
	    		<?php _e('Modify the parent if necessary','xili_tidy_tags');
	     		}	
	     	}	
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
		} elseif (isset($_POST['importacat'])) {
			$action='importacat';
		} elseif (isset($_POST['editor_caps_submit'])) { /* 0.9.5 capabilities */
			$action='editor_caps_submit';	
		} elseif (isset($_POST['action'])) {
			$action=$_POST['action'];	
		}
		
		if (isset($_GET['action'])) :
			$action=$_GET['action'];
			$term_id = $_GET['term_id'];
		endif;
		$message = $action ;
		switch($action) {
			case 'editor_caps_submit';
				$new_cap = $_POST['editor_caps'];
				global $wp_roles;
				$role = $wp_roles->get_role('editor');
				switch ($new_cap) {
					case 'no_caps';
						$wp_roles -> remove_cap('editor','xili_tidy_editor_set');
						$wp_roles -> remove_cap('editor','xili_tidy_editor_group');
						break;
					case 'caps_grouping';
						$role -> add_cap('xili_tidy_editor_group');
						$wp_roles -> remove_cap('editor','xili_tidy_editor_set');
						break;
					case 'caps_setting_grouping';
						$role -> add_cap('xili_tidy_editor_set');
						$role -> add_cap('xili_tidy_editor_group');
						break;
				}
					
				$actiontype = "add";
			    $message .= " - ".__('Editor Capabilities changed to: ','xili_tidy_tags')." (".$new_cap.") ";
				break;
			case 'importacat';
				$chosencatid = $_POST['catsforgroup'];
				$chosenparent = $_POST['tags_parent'];
				$chosencat = get_category($chosencatid);
				$desc = __('Group for: ','xili_tidy_tags').$chosencat->name .' '. __('category','xili_tidy_tags');
				$args = array( 'alias_of' => '', 'description' => $desc, 'parent' => (int) $_POST['tags_parent']);
			    $theids = wp_insert_term( $chosencat->name, TAXOTIDYTAGS, $args);
			    if ( !is_wp_error($theids) )
			    	wp_set_object_terms($theids['term_id'], (int)$_POST['tags_parent'], TAXOTIDYTAGS);
				
				$actiontype = "add";
			    $message .= " - ".__('This group was added: ','xili_tidy_tags')." (".$chosencatid.") parent = ".$chosenparent;
				break;
					
			case 'importxililanguages';
				$this->xili_langs_import_terms ();
				$actiontype = "add";
			    $message .= " - ".__('The languages groups was added.','xili_tidy_tags');
				break;
				
			case 'add';
				$term = $_POST['tagsgroup_name'];
				if ('' != $term) {
					$args = array( 'alias_of' => '', 'description' => $_POST['tagsgroup_description'], 'parent' => (int) $_POST['tagsgroup_parent'], 'slug' => $_POST['tagsgroup_nicename']);
				    $theids = wp_insert_term( $term, TAXOTIDYTAGS, $args);
				    if (!is_wp_error($theids))
				    	wp_set_object_terms($theids['term_id'], (int)$_POST['tagsgroup_parent'], TAXOTIDYTAGS);
					$actiontype = "add";
				    $message .= " - ".__('A new group was added.','xili_tidy_tags');
				} else {
					$actiontype = "add";
				    $message .= " - ".__('NO new group was added.','xili_tidy_tags');
				}
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
				$args = array( 'alias_of' => '', 'description' => $_POST['tagsgroup_description'], 'parent' => (int)$_POST['tagsgroup_parent'], 'slug' =>$_POST['tagsgroup_nicename']);
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
		<div id="xili-tidy-tags-settings" class="wrap" style="min-width:880px">
			<?php screen_icon('tools'); ?>
			<h2><?php _e('Tidy tags groups','xili_tidy_tags') ?></h2>
			<form name="add" id="add" method="post" action="admin.php?page=xili_tidy_tags_settings">
				<input type="hidden" name="action" value="<?php echo $actiontype ?>" />
				<?php wp_nonce_field('xili-tidy-tags-settings'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); 
				/* 0.9.3 add has-right-sidebar for next wp 2.8*/ ?>
				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<div id="side-info-column" class="inner-sidebar">
						<?php do_meta_boxes($this->thehook, 'side', $data); ?>
					</div>
					<div id="post-body" class="has-sidebar has-right-sidebar">
						<div id="post-body-content" class="has-sidebar-content" style="min-width:580px">
					
	   					<?php do_meta_boxes($this->thehook, 'normal', $data); ?>
						</div>
						 	
					<h4><a href="http://dev.xiligroup.com/xili-tidy-tags" title="Plugin page and docs" target="_blank" style="text-decoration:none" ><img style="vertical-align:middle" src="<?php echo WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/xilitidy-logo-32.gif'; ?>" alt="xili-tidy-tags logo"/>  xili-tidy-tags</a> - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php _e('Author'); ?>" >xiligroup.com</a>™ - msc 2009 - v. <?php echo XILITIDYTAGS_VER; ?></h4>
							
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
		    /* since 0.9.5 */
		    if (current_user_can( 'administrator')) { /* all admin only */
		    	$possible = true;
		    } elseif (current_user_can( 'xili_tidy_editor_set')) { /* editor if set */
		    	if ($tagsgroup->term_id == $this->langgroupid || $tagsgroup->parent == $this->langgroupid) {
		    		$possible = false;
		    	} else {
		    		$possible = true;
		    	}	
		    } else {
		    	$possible = false;
		    }
		    	
	    	if (true === $possible ) {
				$edit = "<a href='?page=xili_tidy_tags_settings&amp;action=edit&amp;term_id=".$tagsgroup->term_id."' >".__( 'Edit' )."</a></td>";	
				/* delete link &amp;action=edit&amp;term_id=".$tagsgroup->term_id."*/
				$edit .= "<td><a href='?page=xili_tidy_tags_settings&amp;action=delete&amp;term_id=".$tagsgroup->term_id."' class='delete'>".__( 'Delete' )."</a>";	
	    	} else {
	    		$edit = __('no capability','xili_tidy_tags').'</td><td>';
	    	}		
			
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
	 	extract($data); ?>
		<?php /**/ ?>
					<table class="widefat">
						<thead>
						<tr>
						<th scope="col" style="text-align: center"><?php _e('ID','xili_tidy_tags') ?></th>
	        			<th scope="col"><?php _e('Name','xili_tidy_tags') ?></th>
	        			<th scope="col" width="90" style="text-align: center"><?php _e('Posts') ?></th>
	        			<th colspan="2" style="text-align: center"><?php _e('Group(s) to choose','xili_tidy_tags') ?></th>
						</tr>
						</thead>
						<tbody id="the-list">
							<?php $this->xili_tags_row($tagsnamelike,$tagsnamesearch); /* the lines */?>
						</tbody>
					</table>
					
		<?php
		
	}
	
	function  on_sub_sidebox_3_content($data=array()) { 
	 	extract($data);?>
	 	<p><?php _e('After checking or unchecking do not forget to click update button !','xili_tidy_tags'); ?></p>
		<p class="submit"><input type="submit" class="button-primary" id="update" name="update" value="<?php echo $submit_text ?>" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" name="reset" value="<?php echo $cancel_text ?>" /></p>
		
		<fieldset style="margin:2px; padding:3px; border:1px solid #ccc;"><legend><?php _e('Sub list of tags','xili_tidy_tags'); ?></legend>
			<label for="tagsnamelike"><?php _e('Starting with:','xili_tidy_tags') ?></label> 
			<input name="tagsnamelike" id="tagsnamelike" type="text" value="<?php echo $tagsnamelike; ?>" /><br />
			<label for="tagsnamesearch"><?php _e('Containing:','xili_tidy_tags') ?></label> 
			<input name="tagsnamesearch" id="tagsnamesearch" type="text" value="<?php echo $tagsnamesearch; ?>" />
			<p class="submit"><input type="submit" id="tagssublist" name="tagssublist" value="<?php _e('Sub select…','xili_tidy_tags'); ?>" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<input type="submit" id="notagssublist" name="notagssublist" value="<?php _e('No select…','xili_tidy_tags'); ?>" /></p>
		</fieldset>
		<fieldset style="margin:2px; padding:3px; border:1px solid #ccc;"><legend><?php _e('Groups selection','xili_tidy_tags'); ?></legend>
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
				<br /> <p class="submit"><input type="submit" id="subselection" name="subselection" value="<?php _e('Sub select…','xili_tidy_tags'); ?>" /></p></fieldset><?php
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
	function xili_tags_row($tagsnamelike='',$tagsnamesearch='') {
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
		
		$listtags = get_terms('post_tag', array('hide_empty' => false,'get'=>'all','name__like'=>$tagsnamelike,'search'=>$tagsnamesearch ));
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
			<td> <a href='edit-tags.php?action=edit&amp;tag_ID=".$tag->term_id."'>".$tag->name."</a> </td>
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
		$tagsnamelike = $_POST['tagsnamelike'];
		$tagsnamesearch = $_POST['tagsnamesearch'];
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
		if (isset($_POST['notagssublist'])) {
			$action='notagssublist';
		}
		if (isset($_POST['tagssublist'])) {
			$action='tagssublist';
		}
		if (isset($_GET['action'])) :
			$action = $_GET['action'];
			$term_id = $_GET['term_id'];
		endif;
		$message = $action ;
		switch($action) {
			
			case 'notagssublist';
				$tagsnamelike = '';
				$tagsnamesearch = '';
				$message .= ' no sub list of tags';
				$actiontype = "add";
				break;
			
			case 'tagssublist';
				$message .= ' sub list of tags starting with '.$_POST['tagsnamelike'];
				$actiontype = "add";
				break;
				
			case 'subselection';
				$tagsnamelike = $_POST['tagsnamelike'];
				$tagsnamesearch = $_POST['tagsnamesearch'];
				$message .= ' selection of '.$_POST['tagsgroup_parent_select'];
				$actiontype = "add";
				break;
							
			case 'update';
				$message .= ' ok: datas are saved... ';
				$message .= $this->checkboxes_update_them($tagsnamelike,$tagsnamesearch);
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
		$data = array('xili_tidy_tags_page' => $xili_tidy_tags_page,'message'=>$message,'messagepost'=>$messagepost,'action'=>$action,'submit_text'=>$submit_text,'cancel_text'=>$cancel_text,'term_id'=>$term_id, 'tagsnamesearch'=>$tagsnamesearch, 'tagsnamelike'=>$tagsnamelike);	
			
		/* register the main boxes always available */
		add_meta_box('xili_tidy_tags-sidebox-2', __('Actions','xili_tidy_tags'), array(&$this,'on_sub_sidebox_3_content'), $this->thehook2 , 'side', 'core'); /* Actions */ 
		add_meta_box('xili_tidy_tags-normal-1', __('Tidy Tags','xili_tidy_tags'), array(&$this,'on_sub_normal_1_content'), $this->thehook2 , 'normal', 'core'); /* list of tags*/
			
			?>
		<div id="xili-tidy-tags-assign" class="wrap" style="min-width:880px">
			<?php screen_icon('post'); ?>
			<h2><?php _e('Tags in group','xili_tidy_tags') ?></h2>
			<form name="add" id="add" method="post" action="admin.php?page=xili_tidy_tags_assign">
				<input type="hidden" name="action" value="<?php echo $actiontype ?>" />
				<?php wp_nonce_field('xili-tidy-tags-settings'); ?>
				<?php wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false ); ?>
				<?php wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false ); 
				/* 0.9.3 add has-right-sidebar for next wp 2.8*/ ?>
				<div id="poststuff" class="metabox-holder has-right-sidebar">
					<div id="side-info-column" class="inner-sidebar">
						<?php do_meta_boxes($this->thehook2, 'side', $data); ?>
					</div>
					<div id="post-body" class="has-sidebar has-right-sidebar">
						<div id="post-body-content" class="has-sidebar-content" style="min-width:580px">
					
	   					<?php do_meta_boxes($this->thehook2, 'normal', $data); ?>
						</div>
						 	
					<h4><a href="http://dev.xiligroup.com/xili-tidy-tags" title="Plugin page and docs" target="_blank" style="text-decoration:none" ><img style="vertical-align:middle" src="<?php echo WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)).'/xilitidy-logo-32.gif'; ?>" alt="xili-tidy-tags logo"/>  xili-tidy-tags</a> - © <a href="http://dev.xiligroup.com" target="_blank" title="<?php _e('Author'); ?>" >xiligroup.com</a>™ - msc 2009 - v. <?php echo XILITIDYTAGS_VER; ?></h4>
							
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
		<?php	
	}	
	/*
	 * Update the relationships according CheckBoxes array
	 *
	 */
	function checkboxes_update_them($tagsnamelike='',$tagsnamesearch='') {
	
		$listgroups = get_terms(TAXOTIDYTAGS, array('hide_empty' => false,'get'=>'all'));
		$listtags = get_terms('post_tag', array('hide_empty' => false,'get'=>'all','name__like'=>$tagsnamelike,'search'=>$tagsnamesearch));
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
 * @updated 0.8.2, 1.2
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

		$tags = get_terms_of_groups ($group_id, TAXOTIDYTAGS,'post_tag',array_merge( $r, array( 'orderby' => 'count', 'order' => 'DESC' ))); 
		// Always query top tags - v 1.2
		/* arg $r for sub selection */
		
	}	

	if ( empty( $tags ) )
		return;

	foreach ( $tags as $key => $tag ) {
		if ( 'edit' == $r['link'] )
			$link = get_edit_tag_link( $tag->term_id );
		else
			$link = get_tag_link( $tag->term_id );
		if ( is_wp_error( $link ) )
			return false;

		$tags[ $key ]->link = $link;
		$tags[ $key ]->id = $tag->term_id;
	}

	$return = wp_generate_tag_cloud( $tags, $r ); // Here's where those top tags get sorted according to $args

	//$return = apply_filters( 'wp_tag_cloud', $return, $r );

	if ( 'array' == $r['format'] )
		return $return;

	echo $return;
}

/**
 * the tags for each post in loop 
 * (not in class for general use)
 *
 * @since 1.1 - 
 * @same params as the default the_tags() and and array as fourth param (see [xili_] get_object_terms for details)
 */
function xili_the_tags( $before = null, $sep = ', ', $after = '',$args = array() ) {
	if ( null === $before )
		$before = __('Tags: ');
	if ($args == array()) {	
		echo get_the_tag_list($before, $sep, $after);
	} else {
		echo xili_get_the_term_list($before, $sep, $after, $args); /* no filter tag_list*/
	}
}
/**
 * get_the tag_list for each post in loop $xili_tidy_tags
 * (not in class for general use)
 *
 * @since 1.1 - 
 * @same params as the default the_tags() and and array as fourth param
 */
function xili_get_the_term_list($before, $sep, $after, $args) {
 	global $post;
 	$id = (int) $post->ID;
 	$taxonomy = 'post_tag';
 	/* args analysis */
 	$defaults = array(
		'sub_groups' => ''
	);
	$r = array_merge($defaults, $args);
	extract($r);
 	if ($sub_groups == '') {
		 $terms = get_the_terms( $id, $taxonomy );
 	} else {
 		if (!is_array($sub_groups)) $sub_groups = explode(',',$sub_groups);
 		/* xili - search terms in sub groups */
 		$terms = get_object_term_cache( $id, $taxonomy.implode('-',$sub_groups));
		if ( false === $terms )
 			$terms = get_subgroup_terms_in_post ( $id, $taxonomy, $sub_groups );
 		
 	}
 	if ( is_wp_error( $terms ) )
		return $terms;

	if ( empty( $terms ) )
		return false;

	foreach ( $terms as $term ) {
		$link = get_term_link( $term, $taxonomy );
		if ( is_wp_error( $link ) )
			return $link;
		$term_links[] = '<a href="' . $link . '" rel="tag">' . $term->name . '</a>';
	}

	$term_links = apply_filters( "term_links-$taxonomy", $term_links );

	return $before . join( $sep, $term_links ) . $after;
}

function get_subgroup_terms_in_post ( $id, $taxonomy, $sub_groups ) {
	return xili_get_object_terms ($id,$taxonomy,array('tidy_tags_group'=>TAXOTIDYTAGS, 'sub_groups' => $sub_groups));
}

/**** Functions that improve taxinomy.php ****/

/**
 * get the terms of subgroups of the series objects 
 * (not in class for general use)
 *
 * @since 1.1 - 
 *
 */

function xili_get_object_terms($object_ids, $taxonomies, $args = array()) {
	
	global $wpdb;

	if ( !is_array($taxonomies) )
		$taxonomies = array($taxonomies);

	foreach ( (array) $taxonomies as $taxonomy ) {
		if ( ! is_taxonomy($taxonomy) )
			return new WP_Error('invalid_taxonomy', __('Invalid Taxonomy'));
	}

	if ( !is_array($object_ids) )
		$object_ids = array($object_ids);
	$object_ids = array_map('intval', $object_ids);

	$defaults = array('orderby' => 'name', 'order' => 'ASC', 'fields' => 'all','tidy_tags_group' => '');
	$args = array_merge ( $defaults, $args );
	extract ($args);
	//echo "--- "; print_r($sub_groups);
		
	if (!is_array($sub_groups)) $sub_groups = array($sub_groups);
	foreach ($sub_groups as $tagsgroup) {
		if ($tagsgroup !='') {
			$groupterm = is_term($tagsgroup, $tidy_tags_group); //echo '----'.$tagsgroup;
			$group_ids[] = $groupterm['term_id'];
		}
	}
	$group_ids = array_map('intval', $group_ids);
		$group_ids = implode(', ', $group_ids); /* the terms ID of subgroups are now in list */
		
	$terms = array();
	if ( count($taxonomies) > 1 ) {
		foreach ( $taxonomies as $index => $taxonomy ) {
			$t = get_taxonomy($taxonomy);
			if ( isset($t->args) && is_array($t->args) && $args != array_merge($args, $t->args) ) {
				unset($taxonomies[$index]);
				$terms = array_merge($terms, wp_get_object_terms($object_ids, $taxonomy, array_merge($args, $t->args)));
			}
		}
	} else {
		$t = get_taxonomy($taxonomies[0]);
		if ( isset($t->args) && is_array($t->args) )
			$args = array_merge($args, $t->args);
	}

	extract($args, EXTR_SKIP);

	if ( 'count' == $orderby )
		$orderby = 'tt.count';
	else if ( 'name' == $orderby )
		$orderby = 't.name';
	else if ( 'slug' == $orderby )
		$orderby = 't.slug';
	else if ( 'term_group' == $orderby )
		$orderby = 't.term_group';
	else if ( 'term_order' == $orderby )
		$orderby = 'tr.term_order';
	else if ( 'none' == $orderby ) {
		$orderby = '';
		$order = '';
	} else {
		$orderby = 't.term_id';
	}

	// tt_ids queries can only be none or tr.term_taxonomy_id
	if ( ('tt_ids' == $fields) && !empty($orderby) )
		$orderby = 'tr.term_taxonomy_id';

	if ( !empty($orderby) )
		$orderby = "ORDER BY $orderby";

	$taxonomies = "'" . implode("', '", $taxonomies) . "'";
	$object_ids = implode(', ', $object_ids);

	$select_this = '';
	if ( 'all' == $fields )
		$select_this = 't.*, tt.*';
	else if ( 'ids' == $fields )
		$select_this = 't.term_id';
	else if ( 'names' == $fields )
		$select_this = 't.name';
	else if ( 'all_with_object_id' == $fields )
		$select_this = 't.*, tt.*, tr.object_id';
		
	$subselect = "SELECT st.term_id FROM $wpdb->term_relationships AS str INNER JOIN $wpdb->term_taxonomy AS stt ON str.term_taxonomy_id = stt.term_taxonomy_id INNER JOIN $wpdb->terms AS st ON st.term_id = str.object_id INNER JOIN $wpdb->term_taxonomy AS stt2 ON stt2.term_id = str.object_id WHERE stt.taxonomy IN ('".TAXOTIDYTAGS."') AND stt2.taxonomy = ".$taxonomies." AND stt.term_id IN (".$group_ids.") ";
	//echo $subselect;
	$query = "SELECT $select_this FROM $wpdb->terms AS t INNER JOIN $wpdb->term_taxonomy AS tt ON tt.term_id = t.term_id INNER JOIN $wpdb->term_relationships AS tr ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tt.taxonomy IN ($taxonomies) AND tr.object_id IN ($object_ids) AND t.term_id IN ($subselect) $orderby $order"; //echo $query;

	if ( 'all' == $fields || 'all_with_object_id' == $fields ) {
		$terms = array_merge($terms, $wpdb->get_results($query));
		update_term_cache($terms);
	} else if ( 'ids' == $fields || 'names' == $fields ) {
		$terms = array_merge($terms, $wpdb->get_col($query));
	} else if ( 'tt_ids' == $fields ) {
		$terms = $wpdb->get_col("SELECT tr.term_taxonomy_id FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id WHERE tr.object_id IN ($object_ids) AND tt.taxonomy IN ($taxonomies) $orderby $order");
	}

	if ( ! $terms )
		$terms = array();

	return $terms;
}


/**
 * get terms and add order in term's series that are in a taxonomy 
 * (not in class for general use)
 *
 * @since 0.9.8.2 - 
 * 
 *
 */
if (!function_exists('get_terms_of_groups')) { 
	function get_terms_of_groups ($group_ids, $taxonomy, $taxonomy_child, $order = '') {
		global $wpdb;
		if ( !is_array($group_ids) )
			$group_ids = array($group_ids);
		$group_ids = array_map('intval', $group_ids);
		$group_ids = implode(', ', $group_ids);
		$theorderby = '';
		$where = '';
		$defaults = array('orderby' => 'term_order', 'order' => 'ASC',
		'hide_empty' => true, 'exclude' => '', 'exclude_tree' => '', 'include' => '',
		'number' => '', 'slug' => '', 'parent' => '',
		'name__like' => '',
		'pad_counts' => false, 'offset' => '', 'search' => '');
		
		if (is_array($order)) { // for back compatibility
			$r = &$order;
			$r = array_merge($defaults, $r);
			extract($r);
			
			if ($order == 'ASC' || $order == 'DESC') {
				if ('term_order'== $orderby) {
					$theorderby = ' ORDER BY tr.'.$orderby.' '.$order ;
				} elseif ('count'== $orderby || 'parent'== $orderby) {
					$theorderby = ' ORDER BY tt2.'.$orderby.' '.$order ;
				} elseif ('term_id'== $orderby || 'name'== $orderby) {
					$theorderby = ' ORDER BY t.'.$orderby.' '.$order ;
				}
			}
			
			if ( !empty($name__like) )
			$where .= " AND t.name LIKE '{$name__like}%'";
		
			if ( '' != $parent ) {
				$parent = (int) $parent;
				$where .= " AND tt2.parent = '$parent'";
			}
		
			if ( $hide_empty && !$hierarchical )
				$where .= ' AND tt2.count > 0'; 
			// don't limit the query results when we have to descend the family tree 
			if ( ! empty($number) && '' == $parent ) {
				if( $offset )
					$limit = ' LIMIT ' . $offset . ',' . $number;
				else
					$limit = ' LIMIT ' . $number;
		
			} else {
				$limit = '';
			}
		
			if ( !empty($search) ) {
				$search = like_escape($search);
				$where .= " AND (t.name LIKE '%$search%')";
			}
		
		} else { // for back compatibility
			if ($order == 'ASC' || $order == 'DESC') $theorderby = ' ORDER BY tr.term_order '.$order ;
		}	
		$query = "SELECT t.*, tt2.term_taxonomy_id, tt2.description,tt2.parent, tt2.count, tt2.taxonomy, tr.term_order FROM $wpdb->term_relationships AS tr INNER JOIN $wpdb->term_taxonomy AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id INNER JOIN $wpdb->terms AS t ON t.term_id = tr.object_id INNER JOIN $wpdb->term_taxonomy AS tt2 ON tt2.term_id = tr.object_id WHERE tt.taxonomy IN ('".$taxonomy."') AND tt2.taxonomy = '".$taxonomy_child."' AND tt.term_id IN (".$group_ids.") ".$where.$theorderby.$limit;
		
		$listterms = $wpdb->get_results($query);
		if ( ! $listterms )
			return array();

		return $listterms;
	}
}

/*
 * class for tidy tags cloud
 * @since 0.9.0  
 *
 */
class xili_tidy_tags_cloud_widget {

	function xili_tidy_tags_cloud_widget () {
		load_plugin_textdomain('xili_tidy_tags', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)),dirname(plugin_basename(__FILE__))); /* to share the .mo file*/
		add_action('widgets_init', array(&$this, 'init_widget'));
	}

	function init_widget() {
		if ( !function_exists('wp_register_sidebar_widget') || !function_exists('wp_register_widget_control') )
			return;
	
		$widget_ops = array('classname' => 'xili_tidy_tags_cloud_widget', 'description' => __( "Cloud of grouped tags by xili-tidy-tags plugin",'xili_tidy_tags') );
		//register_sidebar_widget(array('xili_tidy_tags_cloud_widget','widgets'),array(&$this, 'widget'));
		wp_register_sidebar_widget('xili_tidy_tags_cloud_widget',__('Tidy tags cloud','xili_tidy_tags'),array(&$this, 'widget'),$widget_ops);
		// register_widget_control(array('xili_tidy_tags_cloud_widget', 'widgets'), array(&$this, 'widget_options'));
		wp_register_widget_control('xili_tidy_tags_cloud_widget',__('Tidy tags cloud','xili_tidy_tags'),array(&$this, 'widget_options'),$widget_ops);
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
		
		if (abs((int) $options['smallest']>0)) $cloudsargs[] = 'smallest='.abs((int) $options['smallest']);
		if (abs((int) $options['largest']>0)) $cloudsargs[] = 'largest='.abs((int) $options['largest']);
		if (abs((int) $options['number']>0)) $cloudsargs[] = 'number='.abs((int) $options['number']);
		
		if ('no' != $options['orderby'] ) $cloudsargs[] = 'orderby='.$options['orderby'];
		if ('no' != $options['order'] ) $cloudsargs[] = 'order='.$options['order'];
		
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
			$options['smallest'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_smallest"]));
			$options['largest'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_largest"]));
			$options['number'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_number"]));
			$options['orderby'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_orderby"]));
			$options['order'] = strip_tags(stripslashes($_POST["xili_tdtc_widget_order"])); 
			//
			update_option('xili_tidy_tags_cloud_widget_options',$options);
		}
		$options=get_option('xili_tidy_tags_cloud_widget_options');
		echo '<label for="xili_tdtc_widget_title">'.__('Title').': <input id="xili_tdtc_widget_title" name="xili_tdtc_widget_title" type="text" value="'.attribute_escape($options['title']).'" /></label>';
		// other options min max number group 1 and 2 tagsallgroup
		echo '<label for="xili_tdtc_widget_tagsgroup">'.__('Groups','xili_tidy_tags').': <input id="xili_tdtc_widget_tagsgroup" name="xili_tdtc_widget_tagsgroup" type="text" value="'.attribute_escape($options['tagsgroup']).'" /></label>';
		echo '<label for="xili_tdtc_widget_tagsallgroup">'.__('Group #2','xili_tidy_tags').': <input id="xili_tdtc_widget_tagsallgroup" name="xili_tdtc_widget_tagsallgroup" type="text" value="'.attribute_escape($options['tagsallgroup']).'" /></label>';
		
		echo '<br /><label for="xili_tdtc_widget_smallest">'.__('Smallest size','xili_tidy_tags').': <input id="xili_tdtc_widget_smallest" name="xili_tdtc_widget_smallest" type="text" size="3" value="'.attribute_escape($options['smallest']).'" /></label>';
		echo '<label for="xili_tdtc_widget_largest">'.__('Largest size','xili_tidy_tags').': <input id="xili_tdtc_widget_largest" name="xili_tdtc_widget_largest" type="text" size="3" value="'.attribute_escape($options['largest']).'" /></label>';
		echo '<br /><label for="xili_tdtc_widget_number">'.__('Number','xili_tidy_tags').': <input id="xili_tdtc_widget_number" name="xili_tdtc_widget_number" type="text" size="3" value="'.attribute_escape($options['number']).'" /></label>';
		
		
		echo '<fieldset style="margin:2px; padding:3px; border:1px solid #ccc;"><legend>'.__('Order and sorting infos','xili_tidy_tags').'</legend>';
		echo '<br /><select name="xili_tdtc_widget_orderby" id="xili_tdtc_widget_orderby" style="width:100%;"><option value="no" >'.__('no orderby','xili_tidy_tags').'</option>';
		echo '<option value="count" '.(($options['orderby'] == "count") ? 'selected="selected"' :'').' >'.__('count','xili_tidy_tags').'</option>';
		echo '<option value="name" '.(($options['orderby'] == "name") ? 'selected="selected"' :'').' >'.__('name','xili_tidy_tags').'</option></select>';
		
		echo '<select name="xili_tdtc_widget_order" id="xili_tdtc_widget_order" style="width:100%;"><option value="no" >'.__('no order','xili_tidy_tags').'</option>';
		echo '<option value="ASC" '.(($options['order'] == "ASC") ? 'selected="selected"' :'').' >'.__('ASC','xili_tidy_tags').'</option>';
		echo '<option value="DESC" '.(($options['order'] == "DESC") ? 'selected="selected"' :'').' >'.__('DESC','xili_tidy_tags').'</option></select>';
		echo '</fieldset>';
		//
		echo '<input type="hidden" id="xili_tidy_tags_widget_submit" name="xili_tidy_tags_widget_submit" value="1" />';
	}
}

/*
 * class for multiple tidy tags cloud widgets
 * @since 0.9.2  
 *
 */
class xili_tidy_tags_cloud_multiple_widgets {

	function xili_tidy_tags_cloud_multiple_widgets () {
		load_plugin_textdomain('xili_tidy_tags', PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)),dirname(plugin_basename(__FILE__))); /* to share the .mo file*/
		add_action('widgets_init', array(&$this, 'init_widget'));
	}

	function init_widget() {
		if ( !function_exists('wp_register_sidebar_widget') || !function_exists('wp_register_widget_control') )
			return;
		if ( !$options = get_option('xili_tidy_tags_cloud_widgets_options') )
			$options = array();
		$widget_ops = array('classname' => 'xili_tdtc_widget', 'description' => __( "Cloud of grouped tags by xili-tidy-tags plugin",'xili_tidy_tags') );
		$control_ops = array('id_base' => 'xili_tidy_tags_cloud_widget');
		$name = __('Tidy tags cloud','xili_tidy_tags');
		
		$id = false;
		foreach ( (array) array_keys($options) as $o ) {
			$id = "xili_tidy_tags_cloud_widget-$o"; // Never never never translate an id
			wp_register_sidebar_widget($id, $name, array(&$this, 'widget'), $widget_ops, array( 'number' => $o ));
			wp_register_widget_control($id, $name, array(&$this, 'widget_options'), $control_ops, array( 'number' => $o ));
		}

		// If there are none, we register the widget's existance with a generic template
		if ( !$id ) {
			wp_register_sidebar_widget( 'xili_tidy_tags_cloud_widget-1', $name, array(&$this, 'widget'), $widget_ops, array( 'number' => -1 ) );
			wp_register_widget_control( 'xili_tidy_tags_cloud_widget-1', $name, array(&$this, 'widget_options'), $control_ops, array( 'number' => -1 ) );
			
		}
		
	}

	function widget($args, $widget_args = 1) {
		global $wpdb;

		$options = get_option('xili_tidy_tags_cloud_widgets_options');
		extract($args);
		
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		if ( !isset($options[$number]) )
			return;
		
		$cloudsargs = array();
		echo $before_widget.$before_title.__($options[$number]['title'],THEME_TEXTDOMAIN).$after_title;
		if ('the_curlang' == $options[$number]['tagsgroup']) {
			$cloudsargs[] = 'tagsgroup='.the_curlang();
		} elseif ('the_category' == $options[$number]['tagsgroup'])  {	
			$cloudsargs[] = 'tagsgroup='.single_cat_title('',false);
		} else {
			$cloudsargs[] = 'tagsgroup='.$options[$number]['tagsgroup'];
		}
		$cloudsargs[] = 'tagsallgroup='.$options[$number]['tagsallgroup'];
		
		if (abs((int) $options[$number]['smallest']>0)) $cloudsargs[] = 'smallest='.abs((int) $options[$number]['smallest']);
		if (abs((int) $options[$number]['largest']>0)) $cloudsargs[] = 'largest='.abs((int) $options[$number]['largest']);
		if (abs((int) $options[$number]['quantity']>0)) $cloudsargs[] = 'quantity='.abs((int) $options[$number]['quantity']);
		
		if ('no' != $options[$number]['orderby'] ) $cloudsargs[] = 'orderby='.$options[$number]['orderby'];
		if ('no' != $options[$number]['order'] ) $cloudsargs[] = 'order='.$options[$number]['order'];
		
		if (function_exists('xili_tidy_tag_cloud')) { 
			echo '<div class="xilitidytagscloud">';
				xili_tidy_tag_cloud(implode("&",$cloudsargs));
			echo '</div>';
			}
		echo $after_widget;
	}

	function widget_options($widget_args) {
		if ( is_numeric($widget_args) )
			$widget_args = array( 'number' => $widget_args );
		$widget_args = wp_parse_args( $widget_args, array( 'number' => -1 ) );
		extract( $widget_args, EXTR_SKIP );
		
		$options = get_option('xili_tidy_tags_cloud_widgets_options');
		if ( !is_array($options) )
			$options = array();
		
		if ( !$updated && !empty($_POST['sidebar']) ) {
			$sidebar = (string) $_POST['sidebar'];

			$sidebars_widgets = wp_get_sidebars_widgets();
			if ( isset($sidebars_widgets[$sidebar]) )
				$this_sidebar =& $sidebars_widgets[$sidebar];
			else
				$this_sidebar = array();
		
			foreach ( (array) $this_sidebar as $_widget_id ) {
				if ( 'xili_tdtc_widget' == $wp_registered_widgets[$_widget_id]['callback'] && isset($wp_registered_widgets[$_widget_id]['params'][0]['number']) ) {
					$widget_number = $wp_registered_widgets[$_widget_id]['params'][0]['number'];
					if ( !in_array( "xili_tdtc_widget-$widget_number", $_POST['widget-id'] ) ) // the widget has been removed.
					  	unset($options[$widget_number]);
				}
			}
		
			foreach ( (array) $_POST['xili_tdtc_widget'] as $widget_number => $widget_text ) {
				if (isset($widget_text['submit'])) {
					$options[$widget_number]['title'] = strip_tags(stripslashes($widget_text['title']));
					$options[$widget_number]['tagsgroup'] = strip_tags(stripslashes($widget_text['tagsgroup']));
					$options[$widget_number]['tagsallgroup'] = strip_tags(stripslashes($widget_text['tagsallgroup']));
					$options[$widget_number]['smallest'] = strip_tags(stripslashes($widget_text['smallest']));
					$options[$widget_number]['largest'] = strip_tags(stripslashes($widget_text['largest']));
					$options[$widget_number]['quantity'] = strip_tags(stripslashes($widget_text['quantity']));
					$options[$widget_number]['orderby'] = strip_tags(stripslashes($widget_text['orderby']));
					$options[$widget_number]['order'] = strip_tags(stripslashes($widget_text['order'])); 
				}
			}
				update_option('xili_tidy_tags_cloud_widgets_options',$options);
				$updated = true;
		}
			
			
		$options = get_option('xili_tidy_tags_cloud_widgets_options');
		
		if ( -1 == $number ) {
			$title = '';
			$number = '%i%';
			$orderby = "name";
			$order = "ASC";
		} else {
			$title = attribute_escape($options[$number]['title']);
			$tagsgroup = attribute_escape($options[$number]['tagsgroup']);
			$tagsallgroup = attribute_escape($options[$number]['tagsallgroup']);
			$smallest = attribute_escape($options[$number]['smallest']);
			$largest = attribute_escape($options[$number]['largest']);
			$quantity = attribute_escape($options[$number]['quantity']);
			$orderby = $options[$number]['orderby'];
			$order = $options[$number]['order'];
		}
		
		echo '<label for="xili_tdtc_widget_title-'.$number.'">'.__('Title').': <input id="xili_tdtc_widget_title-'.$number.'" name="xili_tdtc_widget['.$number.'][title]" type="text" value="'.$title.'" /></label>';
		// other options min max number group 1 and 2 tagsallgroup
		echo '<label for="xili_tdtc_widget_tagsgroup-'.$number.'">'.__('Groups','xili_tidy_tags').': <input id="xili_tdtc_widget_tagsgroup-'.$number.'" name="xili_tdtc_widget['.$number.'][tagsgroup]" type="text" value="'.$tagsgroup.'" /></label>';
		echo '<label for="xili_tdtc_widget_tagsallgroup-'.$number.'">'.__('Group #2','xili_tidy_tags').': <input id="xili_tdtc_widget_tagsallgroup-'.$number.'" name="xili_tdtc_widget['.$number.'][tagsallgroup]" type="text" value="'.$tagsallgroup.'" /></label>';
		
		echo '<br /><label for="xili_tdtc_widget_smallest-'.$number.'">'.__('Smallest size','xili_tidy_tags').': <input id="xili_tdtc_widget_smallest-'.$number.'" name="xili_tdtc_widget['.$number.'][smallest]" type="text" size="3" value="'.$smallest.'" /></label>';
		echo '<label for="xili_tdtc_widget_largest-'.$number.'">'.__('Largest size','xili_tidy_tags').': <input id="xili_tdtc_widget_largest-'.$number.'" name="xili_tdtc_widget['.$number.'][largest]" type="text" size="3" value="'.$largest.'" /></label>';
		echo '<br /><label for="xili_tdtc_widget_quantity-'.$number.'">'.__('Number','xili_tidy_tags').': <input id="xili_tdtc_widget_quantity-'.$number.'" name="xili_tdtc_widget['.$number.'][quantity]" type="text" size="3" value="'.$quantity.'" /></label>';
		
		
		echo '<fieldset style="margin:2px; padding:3px; border:1px solid #ccc;"><legend>'.__('Order and sorting infos','xili_tidy_tags').'</legend>';
		echo '<br /><select name="xili_tdtc_widget['.$number.'][orderby]" id="xili_tdtc_widget_orderby-'.$number.'" style="width:100%;"><option value="no" >'.__('no orderby','xili_tidy_tags').'</option>';
		echo '<option value="count" '.(($orderby == "count") ? 'selected="selected"' :'').' >'.__('count','xili_tidy_tags').'</option>';
		echo '<option value="name" '.(($orderby == "name") ? 'selected="selected"' :'').' >'.__('name','xili_tidy_tags').'</option></select>';
		
		echo '<select name="xili_tdtc_widget['.$number.'][order]" id="xili_tdtc_widget_order-'.$number.'" style="width:100%;"><option value="no" >'.__('no order','xili_tidy_tags').'</option>';
		echo '<option value="ASC" '.(($order == "ASC") ? 'selected="selected"' :'').' >'.__('ASC','xili_tidy_tags').'</option>';
		echo '<option value="DESC" '.(($order == "DESC") ? 'selected="selected"' :'').' >'.__('DESC','xili_tidy_tags').'</option></select>';
		echo '</fieldset>';
		//
		echo '<input type="hidden" id="xili_tdtc_widget_submit-'.$number.'" name="xili_tdtc_widget['.$number.'][submit]" value="1" />';
		
	} // end options (control)
		
} // end widgets class

/**
 * Shortcode to insert a cloud of a group  of tags inside a post.
 *
 * Example of shortcode : [xili-tidy-tags params="tagsgroup=trademark&largest=10&smallest=10" glue=" | "]
 *
 *@since 1.0
 */
function xili_tidy_tags_shortcode ($atts) {
	$arr_result = shortcode_atts(array('params'=>'', 'glue'=> ' ' ), $atts);
	extract($arr_result);
	return implode($glue, xili_tidy_tag_cloud(html_entity_decode($params)."&format=array")); /* don't use param echo only in 2.8 */
}
add_shortcode('xili-tidy-tags', 'xili_tidy_tags_shortcode');


/**
 * instantiation of xili_tidy_tags class
 *
 * @since 0.8.0 - 0.9.5 =& for instantiation
 *
 * @param metabox (for other posts in post edit UI - beta tests)
 * @param ajax ( true if ajax is activated for post edit admin UI - alpha tests )
 */
 
$xili_tidy_tags =& new xili_tidy_tags (false, false);
 
// comment below line if you don't use widget(s)

//$xili_tidy_tags_cloud_widget = new xili_tidy_tags_cloud_widget (); // only one widget - obsolete -
$xili_tidy_tags_cloud_widgets =& new xili_tidy_tags_cloud_multiple_widgets ();

?>