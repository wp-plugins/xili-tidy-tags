=== xili-tidy-tags ===
Contributors: MS xiligroup
Donate link: http://dev.xiligroup.com/
Tags: tag,tags,theme,post,plugin,posts, page, category, admin,multilingual,taxonomy,dictionary,widget,CMS
Requires at least: 2.7.0
Tested up to: 2.9
Stable tag: 1.3.0 beta

xili-tidy-tags is a tool for grouping tags by semantic groups or by language and for creating tidy tag clouds. 

== Description ==

= on monolingual website (blog or CMS) =
xili-tidy-tags is a tool for grouping tags by semantic groups and sub-groups.
This tags aggregator can also, by instance, be used to group tags according two or more main parts of the CMS website.

= on multilingual website =
xili-tidy-tags is a tool for grouping tags by language with xili-language plugin for multilingual site and for creating tidy tag clouds. By instance to present only tags in english when the theme is in english because the post or the current category present texts in english. Technically, as xili-language, this plugin don't create tables in wordpress db. He only use (rich) taxonomy features. So, with or without the plugin, the base structure is not modified. 


**Template tags** are provided to enrich the theme and display sub-selection of tags.
Through the settings admin UI, it is possible to assign to a tag one or more groups (by instance a french tag to the french language group. A trademark term like WordPress to a group named "trademark" You can choose different storage policies.

= 1.3.0 =
Add sub-selection by tags belonging to a group - or not belonging to this group (suggestion of David). With this way, it is possible to see tags selected in one group and the others there are not. The sub-selection by starting or containing letters remains. Today the columns of group are not sorted or grouped but usables.

**New 1.2.1**

* now quick-edit tag is allowed (keep terms groups)...
* fix default sorting and order in sub-selection by group for `xili_tidy_tag_cloud()` (thanks to Zarban)

**1.1**
In loop, the template tag `the_tags()` named `xili_the_tags` is now available to sub-select tags for the current post from sub-groups. Example of code : 
`
xili_the_tags('',' &bull; ','',array('sub_groups'=>array('trademark', 'software')));
`
With these parameters, only tags from subgroups 'trademark' & 'software' are displayed in loop with each post (use slug of terms). The first three parameters are used like in `the_tags()`. The fourth is an array with an key added to a lot of keys as in taxonomy function `wp_get_object_terms` - see source .

**1.0** add shortcode to include a cloud of a group of tags inside a post, also compatible with new recent WP 2.8.
**Example of shortcode :**  `[xili-tidy-tags params="tagsgroup=trademark&largest=10&smallest=10" glue=" | "]`
In this cas, the group of tags named 'trademark' will be display inside a paragraph of a post. The params are defined as in `xili_tidy_tag_cloud()` and as in `wp_tag_cloud()`. The glue is chars inserted between the tags (if omitted default is a space).

**0.9.5** 
Add capability management for editors role grouping - and setting -. Set by administrator role.
**0.9.4**
When creating tags in post edit UI - this new tag is grouped to default post's lang if xili-language is active and if this tag is not already grouped.
**0.9.2**
add features to modify kindship of tags group, now allows multiple cloud widgets - see note in [installation](http://wordpress.org/extend/plugins/xili-tidy-tags/installation/). 
**0.9.1**
With big tags list in admin UI, select tags starting or containing char(s) or word(s) - possible to split cloud in sub clouds via &offset= et &number= in the var passed to the `xili_tidy_tag_cloud` - .po file completed.
**0.9.0**
The tidy tags cloud widget is available. 
And the template tags `xili_tidy_tag_cloud` (useful for theme's creator) is now more powerful with more args as in `tag_cloud or get_terms`.
Some fixes and translation.
It is also possible to create tag's group according category in three clicks - see second example in installation and [screenshots](http://wordpress.org/extend/plugins/xili-tidy-tags/screenshots/).


== Installation ==

1. Upload the folder containing `xili-tidy-tags.php` and others files to the `/wp-content/plugins/` directory,
2. If xili-language plugin is activated, groups of languages are automatically created. If not, you can also use xili-tidy-tags to group your tags in semantic group like technical, trademark...
3. in theme, a new template tag is available : `xili_tidy_tag_cloud` Same passed values as tag_cloud but two new : tagsgroup and tagsallgroup . tagsallgroup can be the parent group slug, tagsgroup is one of the child group slug. If one or both are included, the cloud is built with sub-selected tags in this (theses) group(s). 


**Exemples of script in sidebar.php :**

= with xili-language plugin activated in multilingual website =
`
<div>
<h2><?php _e('Tags cloud','xilidev');?></h2>
<?php if (function_exists('xili_tidy_tag_cloud') && class_exists('xili_language')) xili_tidy_tag_cloud('tagsgroup='.the_curlang().'&tagsallgroup=tidy-languages-group&largest=18'); ?>
</div>

`

= with semantic group named as category and a group containing trademarks named trademark =
`
<h2><?php _e('Tags cloud','xilidev');?></h2>

<?php 
if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup='.single_cat_title('',false).'&tagsallgroup=trademark&largest=18'); ?>
</div>

`
= example of a splitted tag cloud of authors group (here separated by hr) - change html tags if you want to build a table with 3 columns =
`
<div>
<h2><?php _e('Tags clouds','xilidev');?></h2>
<?php if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup=authors&largest=18&&number=15'); ?>
<hr />
<?php if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup=authors&largest=18&&offset=15&number=15'); ?>
<hr />
<?php if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup=authors&largest=18&&offset=30&number=150'); ?>
</div>
`
= note about template tag =

If the two args tagsgroup and tagsallgroup are empty, the content is all the tags as in current tag cloud but with more features for selecting or look as soon documented.

= note about widget =
If you create the single widget since 0.9.0, with 0.9.2 (which allows more than one), you need to recreate one, two or more widget(s) in theme admin UI.


== Frequently Asked Questions ==

= Where can I see websites using this plugin ? =

dev.xiligroup.com [here](http://dev.xiligroup.com/ "a multi-language site")

and

www.xiliphone.mobi [here](http://www.xiliphone.mobi "a theme for mobile") also usable with mobile as iPhone.

and the first from China since plugin version 0.8.0

layabozi.com [here](http://layabozi.com) to sub select music maker name and other tags sub-groups.

= Compatibility with other plugins ? =

In xiligroup plugins series, xili-tidy-tags is compatible with [xili-language](http://wordpress.org/extend/plugins/xili-language/), [xili-dictionary](http://wordpress.org/extend/plugins/xili-dictionary/), [xilitheme-select](http://wordpress.org/extend/plugins/xilitheme-select/) , a set of plugins to create powerful multilingual CMS website.

= Compatibility with WP 2.8 ? =

Today, with current release, xili-tidy-tags is compatible with 2.8 version.

== Screenshots ==

1. the admin settings UI : tidy tags groups and arrow on what is new.
2. the admin settings UI : table and checkboxes to set group of tags.
3. the admin settings UI : table and checkboxes to set group of tags : sub-selection of groups.
4. widget UI : example where cloud of tags is dynamic and according categories and include group trademark.
5. widget UI : (xili-language plugin activated) example where cloud of tags is dynamic and according language.
6. widget UI : display a sub-group of tags named philosophy.
7. the admin settings UI : with big tags list, it is now possible to select tags starting or containing char(s) or word(s).

== Changelog ==

= 1.2.1 = fix quick-edit tag function.
= 1.2 = fix `xili_tidy_tag_cloud` sort and order.
= 1.1 = In loop, the template tag `the_tags` named `xili_the_tags` is now able to show only tags of sub-group(s).
= 1.0.1 = some fixes in php code on some servers (Thanks to Giannis)
= 1.0 = 
* add shortcode to include a cloud of a group of tags inside a post,
* compatible with WP 2.8.
= 0.9.5 = Capabilities and roles, better admin menu
= 0.9.4 = when creating tags in post UI - group new tag to default lang if xili-language is active
= 0.9.3 = W3C, recover compatibility with future WP 2.8
= 0.9.2 = changing kindship, now allows multiple cloud widgets.
= 0.9.1 = with big tags list, select tags starting or containing char(s) or word(s). &offset= et &number= in `xili_tidy_tag_cloud`
= 0.9.0 = widget for compatible themes and UI actions to include group according a chosen category
= 0.8.2 = fixes php warning when tagsgroup args are empty in tidy_tag_cloud()
= 0.8.1 = some fixes - improved query - better tag_cloud()
= 0.8.0 = first public beta release.

© 2009-11-29 dev.xiligroup.com

== More infos ==

= Capabilities and roles : =

0.9.5 : Administrator role can create grouping or setting capabilities for editor role. 'Grouping' permits to editor to group tags in group (lang and/or semantic). 'Setting' permits to editor to create, modify or delete semantic groups. Only administrator has access to languages groups. 


This first beta releases are for multilingual or cms website's creator or designer.

The plugin post is frequently documented [dev.xiligroup.com](http://dev.xiligroup.com/)
and updated [Wordpress repository](http://wordpress.org/extend/plugins/xili-tidy-tags/).

See also the [Wordpress plugins forum](http://wordpress.org/tags/xili-tidy-tags/).


© 091129 - MS - dev.xiligroup.com
