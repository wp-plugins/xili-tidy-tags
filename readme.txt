=== xili-tidy-tags ===
Contributors: MS xiligroup
Donate link: http://dev.xiligroup.com/
Tags: tag,tags,theme,post,plugin,posts, page, category, admin,multilingual,taxonomy,dictionary
Requires at least: 2.7.0
Tested up to: 2.7.1
Stable tag: 0.9.0

xili-tidy-tags is a tool for grouping tags by language or semantic group. 

== Description ==

xili-tidy-tags is a tool for grouping tags by language with xili-language plugin for multilingual site. By instance to present only tags in english when the theme is in english because the post or the current category present texts in english. But this tags aggregator can also, by instance, be used to group tags according two or more main parts of the CMS website.
Technically, as xili-language, this plugin don't create tables in wordpress db. He only use (rich) taxonomy features. So, with or without the plugin, the base structure is not modified. **Template tags** are provided to enrich the theme and display sub-selection of tags.
Through the settings admin UI, it is possible to assign to a tag one or more groups (by instance a french tag to the french language group. You can choose different storage policies.

= New 0.9.0 =

The tidy tags cloud widget is available. 
And the template tags `xili_tidy_tag_cloud` (useful for theme's creator) is now more powerful with more args as in `tag_cloud or get_terms`.
Some fixes and translation.
It is also possible to create tag's group according category in three clicks - see second example in installation and [screenshots](http://wordpress.org/extend/plugins/xili-tidy-tags/screenshots/).


THIS VERSION 0.9.0 IS A BETA VERSION

== Installation ==

1. Upload the folder containing `xili-tidy-tags.php` and others files to the `/wp-content/plugins/` directory,
2. If xili-language plugin is activated, groups of languages are automatically created. If not, you can also use xili-tidy-tags to group your tags in semantic group like technical, trademark...
3. in theme, a new template tag is available : `xili_tidy_tag_cloud` Same passed values as tag_cloud but two new : tagsgroup and tagsallgroup . tagsallgroup can be the parent group slug, tagsgroup is one of the child group slug. If one or both are included, the cloud is built with sub-selected tags in this (theses) group(s). 

**Exemples of script in sidebar.php :**

= with xili-language plugin activated in multilingual website =
`
<div>
<h2><?php _e('Tags cloud','xilidev');?></h2>
<?php if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup='.the_curlang().'&tagsallgroup=tidy-languages-group&largest=18'); ?>
</div>

`

= with semantic group named as category and a group containing trademarks named trademark =
`
<h2><?php _e('Tags cloud','xilidev');?></h2>

<?php 
if (function_exists('xili_tidy_tag_cloud')) xili_tidy_tag_cloud('tagsgroup='.single_cat_title('',false).'&tagsallgroup=trademark&largest=18'); ?>
</div>

`
= note about template tag =

If the two args tagsgroup and tagsallgroup are empty, the content is all the tags as in current tag cloud but with more features for selecting or look as soon documented.

== Frequently Asked Questions ==

= Where can I see websites using this plugin ? =

dev.xiligroup.com [here](http://dev.xiligroup.com/ "a multi-language site")

and

www.xiliphone.mobi [here](http://www.xiliphone.mobi "a theme for mobile") also usable with mobile as iPhone.

and the first from China since plugin version 0.8.0

layabozi.com [here](http://layabozi.com) to sub select music maker name and other tags sub-groups.

= Next steps ? =

More admin UI tools for bulk actions with tags groups....


== Screenshots ==

1. the admin settings UI : tidy tags groups
2. the admin settings UI : table and checkboxes to set group of tags.
3. the admin settings UI : table and checkboxes to set group of tags : sub-selection of groups.
4. widget UI : example where cloud of tags is dynamic and according categories and include group trademark.
5. widget UI : (xili-language plugin activated) example where cloud of tags is dynamic and according language.
6. widget UI : display a sub-group of tags named philosophy.

== More infos ==

This first beta releases are for multilingual or cms website's creator or designer.

The plugin post is frequently documented [dev.xiligroup.com](http://dev.xiligroup.com/)
and updated [Wordpress repository](http://wordpress.org/extend/plugins/xili-tidy-tags/).

See also the [Wordpress plugins forum](http://wordpress.org/tags/xili-tidy-tags/).

= 0.9.0 = widget for compatible themes and UI actions to include group according a chosen category
= 0.8.2 = fixes php warning when tagsgroup args are empty in tidy_tag_cloud()
= 0.8.1 = some fixes - improved query - better tag_cloud()
= 0.8.0 = first public beta release.

© 090404 - MS - dev.xiligroup.com
