# M2Commerce: Magento 2 Media Cleaner

## Description

The module provides a command for retrieving information about catalog media files and allow you to remove unused and duplicate media.

```
bin/magento m2commerce:cleanmedia

Media Gallery entries: 3416.
Files in directory: 1478.
Cached images: 3285.
Unused files: 683.
Duplicated files: 751.
```

The following options include more details in the output:
- list all unused files with `-u` option
- list all duplicated files with `-d` option

Also, it allows to clean up filesystem and db:
- remove unused files with `-r` option
- remove duplicated files and replace references in database with `-x` option

## Installation
### Magento® Marketplace

This extension will also be available on the Magento® Marketplace when approved.

1. Go to Magento® 2 root folder
2. Require/Download this extension:

   Enter following commands to install extension.

   ```
   composer require m2commerce/media-cleaner"
   ```

   Wait while composer is updated.

   #### OR

   You can also download code from this repo under Magento® 2 following directory:

    ```
    app/code/M2Commerce/MediaCleaner
    ```    

3. Enter following commands to enable the module:

   ```
   php bin/magento module:enable M2Commerce_MediaCleaner
   php bin/magento setup:upgrade
   php bin/magento setup:di:compile
   php bin/magento cache:clean
   php bin/magento cache:flush
   ```

4. If Magento® is running in production mode, deploy static content:

   ```
   php bin/magento setup:static-content:deploy
   ```
