=== xili-tidy-tags ===
Contributors: MS xiligroup
Donate link: http://dev.xiligroup.com/
Tags: tag,tags,theme,post,plugin,posts, page, category, admin,multilingual,taxonomy,dictionary
Requires at least: 2.7.0
Tested up to: 2.7.1
Stable tag: 0.8.0

xili-tidy-tags is a tool for grouping tags by language or semantic group. 

== Description ==

xili-tidy-tags is a tool for grouping tags by language with xili-language plugin for multilingual site. By instance to present only tags in english when the theme is in english because the post or the current category present texts in english. But this tags aggregator can also, by instance, be used to group tags according two or more main parts of the CMS website.
Technically, as xili-language, this plugin don't create tables in wordpress db. He only use (rich) taxonomy features. So, with or without the plugin, the base structure is not modified. **Template tags** are provided to enrich the theme and display sub-selection of tags.
Through the settings admin UI, it is possible to assign to a tag one or more groups (by instance a french tag to the french language group. You can choose different storage policies.

THIS VERSION 0.8.0 IS A BETA VERSION (running on our sites) - WE NEED FEEDBACK - coded as OOP and new admin UI WP 2.7 features (meta_box, js, screen options,...)

== Installation ==

1. Upload the folder containing `xili-tidy-tags.php` and others files to the `/wp-content/plugins/` directory,
2. If xili-language plugin is activated, groups of languages are automatically created. If not, you can also use xili-tidy-tags to group your tags in semantic group like technical, trademark...
3. more details soon...
4. in theme, a new template tag is available : **xili_tidy_tag_cloud** Same passed values as tag_cloud but two new : tagsgroup and tagsallgroup . tagsallgroup is the parent group slug, tagsgroup is one of the child group slug. If one or both are included, the cloud is sub-selected in this group. 

== Frequently Asked Questions ==

= Where can I see websites using this plugin ? =

dev.xiligroup.com [here](http://dev.xiligroup.com/ "a multi-language site")

and

www.xiliphone.mobi [here](http://www.xiliphone.mobi "a theme for mobile") also usable with mobile as iPhone.

== Screenshots ==

1. the admin settings UI : tidy tags groups
2. the admin settings UI : table and checkboxes to set group of tags.
3. the admin settings UI : table and checkboxes to set group of tags : sub-selection of groups.

== More infos ==

This first beta releases are for multilingual or cms website's creator or designer.

The plugin post is frequently updated [dev.xiligroup.com](http://dev.xiligroup.com/)

See also the [Wordpress plugins forum](http://wordpress.org/tags/xili-tidy-tags/).

= 0.8.O = first public beta release.

© 090329 - MS - dev.xiligroup.com
