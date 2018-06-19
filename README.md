osTicket-SuiteCRM
==============
An plugin for [osTicket](https://osticket.com) which creates cases on [SuiteCRM](https://suitecrm.org).

Originally forked from: [https://github.com/clonemeagain/osticket-slack](https://github.com/clonemeagain/osticket-slack).

Info
------
This plugin uses CURL and was designed/tested with osTicket-1.10.1

## Requirements
- php_curl
- A SuiteCRM installation with an available JSON API (v. 7.10.4 and higher recommended)

## Install
--------
1. Clone this repo or download the zip file and place the contents into your `include/plugins` folder.
1. Now the plugin needs to be enabled & configured, so login to osTicket, select "Admin Panel" then "Manage -> Plugins" you should be seeing the list of currently installed plugins.
1. Click on `SuiteCRM Notifier` and paste your SuiteCRM base URL into the box (SuiteCRM setup instructions below).
1. Click `Save Changes`! (If you get an error about curl, you will need to install the Curl module for PHP).
1. After that, go back to the list of plugins and tick the checkbox next to "SuiteCRM Notifier" and select the "Enable" button.


## SuiteCRM Setup:
- Navigate to your SuiteCRM installation using an administrator account.
- Create an Auth login. Take note of the Auth Secret before saving.
- After saving, take note of the ID generated.
- Paste this info into the `osTicket -> Admin -> Plugin -> SuiteCRM` config admin screen.

## Test!
Create a ticket!

You should see something like the following appear in your Slack channel:

![slack-new-ticket](https://user-images.githubusercontent.com/5077391/31572647-923e07b0-b0f6-11e7-9515-98205d6f800f.png)

When a user replies, you'll get something like:

![slack-reply](https://user-images.githubusercontent.com/5077391/31572648-9279eb18-b0f6-11e7-97da-9a9c63a200d4.png)

## Adding pull's from original repo:
 +0.2 - 17 december 2016
 +[feature] "Ignore when subject equals regex" by @ramonfincken
