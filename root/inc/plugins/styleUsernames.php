<?php
/**
 * This file is part of Style Usernames plugin for MyBB.
 * Copyright (C) Lukasz Tkacz <lukasamd@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Disallow direct access to this file for security reasons
 * 
 */
if (!defined("IN_MYBB")) exit;

/**
 * DEFINE PLUGINLIBRARY
 *
 *   Define the path to the plugin library, if it isn't defined yet.
 */
if(!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT."inc/plugins/pluginlibrary.php");
}

/**
 * Add plugin hooks
 * 
 */
$plugins->add_hook('admin_config_plugins_begin', ['styleUsernames', 'admin']);
$plugins->add_hook('global_end', ['styleUsernames', 'getModerators']);
$plugins->add_hook('pre_output_page', ['styleUsernames', 'parseUsernames']);

/**
 * Standard MyBB info function
 * 
 */
function styleUsernames_info()
{
    global $mybb, $lang, $plugins_cache;

    $lang->load("styleUsernames");
    
    $lang->styleUsernamesDesc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' . 
        '<input type="hidden" name="hosted_button_id" value="3BTVZBUG6TMFQ">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->styleUsernamesDesc;

    $info = array(
        'name' => $lang->styleUsernamesName,
        'description' => $lang->styleUsernamesDesc,
        'website' => 'https://lukasztkacz.com',
        'author' => 'Lukasz "LukasAMD" Tkacz',
<<<<<<< HEAD
        'authorsite' => 'https://lukasztkacz.com',
        'version' => '1.2.0',
=======
        'authorsite' => 'http://lukasztkacz.com',
        'version' => '1.0.0',
>>>>>>> parent of e65e44c... Version 1.1.0
        'guid' => '',
        'compatibility' => '18*',
        'codename' => 'style_usernames'
    );
    
    // Display some extra information when installed and active.
    if ($plugins_cache['active']['styleUsernames']) {
        global $PL;
        $PL or require_once PLUGINLIBRARY;
        
        $url = 'index.php';
        $text1 = $text2 = '';   
        if (styleUsernames::inject('edit') !== true) {
            $url = $PL->url_append($url, array("styleUsernames" => "edit"));
            $text1 = '<strong style="color:red;">' . $lang->styleUsernamesEditFail . '</strong>';
            $text2 = $lang->styleUsernamesEditFailLink;
        } else {
            $url = $PL->url_append($url, array("styleUsernames" => "undo"));
            $text1 = '<strong style="color:green;">' . $lang->styleUsernamesEditOk . '</strong>';
            $text2 = $lang->styleUsernamesEditOkLink;
        }
        
        $url = $PL->url_append($url, array("module" => "config-plugins"));
        $url = $PL->url_append($url, array("my_post_key" => $mybb->post_code));

        $info["description"] .= "<p>{$text1}<br /><a href=\"{$url}\">{$text2}</a>.</p>";
    }
    
    return $info;
}

function styleUsernames_activate() 
{
    if (!file_exists(PLUGINLIBRARY)) {
        flash_message("The selected plugin could not be installed because <a href=\"http://mods.mybb.com/view/pluginlibrary\">PluginLibrary</a> is missing.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
    styleUsernames::inject('edit', true);
}


function styleUsernames_deactivate()
{
    global $PL;
    $PL or require_once PLUGINLIBRARY;

    styleUsernames::inject('undo', true);
    $PL->cache_delete("styleUsernames");
}



/**
 * Plugin Class
 */
class styleUsernames 
{
    static $cache = array(
        'groups' => array(),
        'mods' => array(),
        'users' => array(),
        'guests' => array(),
    );
    
    /**
     * ACP plugin action
     * 
     */
    public static function admin()
    {
<<<<<<< HEAD
        global $mybb, $lang;
        $lang->load("styleUsernames");
    
        if ($mybb->input['my_post_key'] != $mybb->post_code) {
            return;
        }
        
        if ($mybb->input['my_post_key'] != $mybb->post_code
            || empty($mybb->input['styleUsernames']) 
            || !in_array($mybb->input['styleUsernames'], array('edit', 'undo'))
        ) {
            return;
        }
        
        $action = $mybb->input['styleUsernames']; 
        $result = self::inject($action, true);
        
        if($result === true) {
            flash_message(sprintf($lang->styleUsernamesFileOk, 'inc/functions.php'), "success");
            admin_redirect("index.php?module=config-plugins");
        }
    
        else {
            flash_message(sprintf($lang->styleUsernamesFileFail, 'inc/functions.php'), "error");
            admin_redirect("index.php?module=config-plugins");
        }
        
    }
    
    /**
     * Edit MyBB core files
     * 
     * @param string mode Edit or undo changes
     * @param boolean apply Hard or soft changes
     */
    public static function inject($mode = '', $apply = false) 
    {
        global $PL, $mybb;
        $PL or require_once PLUGINLIBRARY;
        
        $edits = array();
        if ($mode === 'edit') {
            $edits = array(
                'search' => '// Build the profile link for the registered user',
                'after' => array(
                    'styleUsernames::addToCache($username, $uid);',
                    '$username = "#STYLE_USERNAMES_UID{$uid}#";',
                ),
            ); 
            $result = $PL->edit_core('styleUsernames', 'inc/functions.php', $edits, $apply);
        }
        
        $result = $PL->edit_core('styleUsernames', 'inc/functions.php', $edits, $apply);
        return $result;
    }
    
    /**
     * Add data to cache.
     */
    public static function addToCache($username, $uid) 
    {
        self::$cache['users'][$uid] = $username; 
    }    
=======
        global $plugins;

        $plugins->hooks["global_end"][10]["styleUsernames_getModerators"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->getModerators();'));
        $plugins->hooks["pre_output_page"][10]["styleUsernames_parseUsernames"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->parseUsernames($arg);'));
        $plugins->hooks["build_forumbits_forum"][10]["styleUsernames_buildForumbits"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->buildForumbits($arg);'));
        $plugins->hooks["forumdisplay_announcement"][10]["styleUsernames_forumdisplayAnnouncement"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->forumdisplayAnnouncement();'));
        $plugins->hooks["forumdisplay_thread"][10]["styleUsernames_forumdisplayThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->forumdisplayThread();'));
        $plugins->hooks["search_results_thread"][10]["styleUsernames_searchThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->searchThread();'));
        $plugins->hooks["search_results_post"][10]["styleUsernames_searchPost"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->searchPost();'));
        $plugins->hooks["pre_output_page"][10]["styleUsernames_pluginThanks"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->pluginThanks($arg);'));
    }
>>>>>>> parent of e65e44c... Version 1.1.0

    /**
     * Change moderators usernames to Style Usernames code and get their ids.
     */
    public static function getModerators() {
        global $cache;

        if (empty($cache->cache['moderators'])) {
            $cache->cache['moderators'] = $cache->read("moderators");
        }

        foreach ($cache->cache['moderators'] as $fid => $fdata) {
            if (isset($fdata['usergroups'])) {
                foreach ($fdata['usergroups'] as $gid => $gdata) {
                    $cache->cache['moderators'][$fid]['usergroups'][$gid]['title'] = "#STYLE_USERNAMES_GID{$gid}#";
                    self::$cache['groups'][] = $gid;
                }
            }
            if (isset($fdata['users'])) {
                foreach ($fdata['users'] as $uid => $udata) {
                    $cache->cache['moderators'][$fid]['users'][$uid]['username'] = "#STYLE_USERNAMES_UID{$uid}#";
                    self::$cache['users'][$uid] = $udata['username'];
                    self::$cache['mods'][] = $uid;
                }
            }
        }
    }

    /**
     * Parse all usernames using build-in uids cache - modify output code
     * 
     * @param string &$content Reference to output code    
     */
    public static function parseUsernames(&$content) {
        global $db, $cache;

        // Parse users
        self::$cache['users'] = array_unique(self::$cache['users']);
        self::$cache['guests'] = array_unique(self::$cache['guests']);
        self::$cache['mods'] = array_unique(self::$cache['mods']);

        if (sizeof(self::$cache['users'])) {
            $result = $db->simple_select('users', 'uid, username, usergroup, displaygroup', 
                                        'uid IN (' . implode(',', array_keys(self::$cache['users'])) . ')');
            while ($row = $db->fetch_array($result)) {
                $username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
                $sign = "#STYLE_USERNAMES_UID{$row['uid']}#";

                // Delete old code - only for moderators (fix for images in usergroup style)
                if (in_array($row['uid'], self::$cache['mods'])) {
                    $old_username = str_replace('{username}', $sign, $cache->cache['usergroups'][$row['usergroup']]['namestyle']);
                    if ($old_username != '') {
                        $content = str_replace($old_username, $sign, $content);
                    }
                }

                $content = str_replace($sign, $username, $content);
                unset(self::$cache['users'][$row['uid']]);
            }

            // Clean output for bad (non-isset) usernames
            if (isset($fdata['users'])) {
                foreach ($fdata['users'] as $uid => $udata) {
                    $cache->cache['moderators'][$fid]['users'][$uid]['username'] = "#STYLE_USERNAMES_UID{$uid}#";
                    self::$cache['users'][$uid] = $udata['username'];
                    self::$cache['mods'][] = $uid;
                }
            }
        }
        
        // Parse guests
        if (sizeof(self::$cache['guests'])) {
            foreach (self::$cache['guests'] as $username) {
                $sign = "#STYLE_USERNAMES_UID{$username}#";
                $username = format_name($username, 1, 1);
                $content = str_replace($sign, $username, $content);
            }
        }
        
        // Parse moderator groups
        self::$cache['groups'] = array_unique(self::$cache['groups']);

        if (sizeof(self::$cache['groups'])) {
            foreach ($cache->cache['usergroups'] as $gid => $gdata) {
                if (!in_array($gid, self::$cache['groups'])) {
                    continue;
                }
                $title = format_name($gdata['title'], $gid);
                $sign = "#STYLE_USERNAMES_GID{$gid}#";
                $content = str_replace($sign, $title, $content);
            }
        }
    }
<<<<<<< HEAD
 
=======

    /**
     * Style usernames on forums list
     * 
     * @param array &$forum Reference to forum data
     */
    public function buildForumbits(&$forum)
    {
        if ($forum['lastposteruid'] != 0)
        {
            $this->cache['users'][$forum['lastposteruid']] = $forum['lastposter'];
            $forum['lastposter'] = "#STYLE_USERNAMES_UID{$forum['lastposteruid']}#";
        }
        else
        {
            $this->cache['guests'][] = $forum['lastposter'];
            $forum['lastposter'] = "#STYLE_USERNAMES_UID{$forum['lastposter']}#";
        }
    }

    /**
     * Style usernames on announcements list
     */
    public function forumdisplayAnnouncement()
    {
        global $announcement;

        $this->cache['users'][$announcement['uid']] = $announcement['username'];
        $sign = ">#STYLE_USERNAMES_UID{$announcement['uid']}#<";
        $announcement['profilelink'] = str_replace(">{$announcement['username']}<", $sign, $announcement['profilelink']);
    }

    /**
     * Style usernames on topics list
     */
    public function forumdisplayThread()
    {
        global $thread;
        
        if ($thread['username'])
        {
            $this->cache['users'][$thread['uid']] = $thread['username'];
            $thread['username'] = "#STYLE_USERNAMES_UID{$thread['uid']}#";
        }
        else
        {
            $this->cache['guests'][] = $thread['threadusername'];
            $thread['username'] = "#STYLE_USERNAMES_UID{$thread['threadusername']}#";
        }

        if ($thread['lastposteruid'] != 0)
        {
            $this->cache['users'][$thread['lastposteruid']] = $thread['lastposter'];
            $thread['lastposter'] = "#STYLE_USERNAMES_UID{$thread['lastposteruid']}#";
        }
        else
        {
            $this->cache['guests'][] = $thread['lastposter'];
            $thread['lastposter'] = "#STYLE_USERNAMES_UID{$thread['lastposter']}#";
        }
    }

    /**
     * Style usernames on topics list (search results)
     */
    public function searchThread()
    {
        global $thread, $lastposterlink;

        if ($thread['username'])
        {
            if ($thread['uid'] != 0)
            {
                $this->cache['users'][$thread['uid']] = $thread['username'];
                $sign = ">#STYLE_USERNAMES_UID{$thread['uid']}#<";
                $thread['profilelink'] = str_replace(">{$thread['username']}<", $sign, $thread['profilelink']);
            }
            else
            {
                $this->cache['guests'][] = $thread['username'];
                $thread['profilelink'] = "#STYLE_USERNAMES_UID{$thread['username']}#";
            }

        }


        if ($thread['lastposteruid'] != 0)
        {
            $this->cache['users'][$thread['lastposteruid']] = $thread['lastposter'];
            $sign = ">#STYLE_USERNAMES_UID{$thread['lastposteruid']}#<";
            $lastposterlink = str_replace(">{$thread['lastposter']}<", $sign, $lastposterlink);
        }
        else
        {
            $this->cache['guests'][] = $thread['lastposter'];
            $lastposterlink = "#STYLE_USERNAMES_UID{$thread['lastposter']}#";
        }
    }

    /**
     * Style usernames on posts list (search results)
     */
    public function searchPost()
    {
        global $post;

        if ($post['uid'])
        {
            $this->cache['users'][$post['uid']] = $post['username'];
            $sign = ">#STYLE_USERNAMES_UID{$post['uid']}#<";
            $post['profilelink'] = str_replace(">{$post['username']}<", $sign, $post['profilelink']);
        }
        else
        {
            $this->cache['guests'][] = $post['username'];
            $post['profilelink'] = "#STYLE_USERNAMES_UID{$post['username']}#"; 
        }
    }
    
>>>>>>> parent of e65e44c... Version 1.1.0
    /**
     * Say thanks to plugin author - paste link to author website.
     * Please don't remove this code if you didn't make donate
     * It's the only way to say thanks without donate :)     
     */
    public static function pluginThanks(&$content) 
    {
        global $session, $lukasamd_thanks;
        
<<<<<<< HEAD
        if (!isset($lukasamd_thanks) && $session->is_spider) {
            $thx = '<div style="margin:auto; text-align:center;">This forum uses <a href="https://lukasztkacz.com">Lukasz Tkacz</a> MyBB addons.</div></body>';
=======
        if (!isset($lukasamd_thanks) && $session->is_spider)
        {
            $thx = '<div style="margin:auto; text-align:center;">This forum uses <a href="http://lukasztkacz.com">Lukasz Tkacz</a> MyBB addons.</div></body>';
>>>>>>> parent of e65e44c... Version 1.1.0
            $content = str_replace('</body>', $thx, $content);
            $lukasamd_thanks = true;
        }
    }
    
}