TCM-Filmfestival-Live-Gallery
=============================

Live gallery plugin for the TCM Filmfestival website. This plugin allows a user to directly upload (probably via FTP) to a directory within an existing Wordpress build. The plugin will then read though all of the uploaded images in the directory, resize them, extract the metadata, and insert them into the Wordpress media gallery as well as register them as posts in a custom created post type that is created unpon plugin registration. A cron task must also be manually created in order to process the uploaded images automatically. The image metadata structure should be as follows:

post title = $metaData["xmp"]["dc"]["title"][0].': '.$metaData["xmp"]["dc"]["description"][0];
post content = $metaData["xmp"]["dc"]["description"][0];
post author = $metaData["xmp"]["dc"]["creator"][0];
post category = strtolower($metaData["xmp"]["photoshop"]["Category"]);
post create date = strtotime($metaData["xmp"]["photoshop"]["DateCreated"]);

Speifying a post category will insert the post as a custom taxonomy type within the custom post type. If no post category is defined, it will go into a taxonomy type named "undefined".

NOTES
=============================
This plugin was pretty much written overnight. It works well for the exact instance it was created for, filmfestival.tcm.com, btu leave a lot to be desired for usability. A few ideas:

1. Create a settings page that you can specify the meta data structure and cron settings.
2. Extend plugin so processed images can be added to any post type.
3. Extend plugin to work with some existing popular Wordpress Galleries such as nextgen and galleria.
