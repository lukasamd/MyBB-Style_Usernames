# Style Usernames for MyBB

This plugin colorizes usernames according to the group name style settings.
It can format nicknames in:

* moderators list
* topic author in forum view
* last post author in forum view
* sender in private messages list
* news author in portal view
* topic author in search results
* last posts author in search results
* announcements author

## Compatibility

Version 2.x targets current MyBB 1.8.x releases and PHP 8.x. It removes the legacy `create_function()` hooks, escapes usernames before applying group styles, and removes the old spider-only author link injection.
