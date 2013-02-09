<?php
/**
 * This file is part of Style Usernames plugin for MyBB.
 * Copyright (C) 2010-2013 Lukasz Tkacz <lukasamd@gmail.com>
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
 * Create plugin object
 * 
 */
$plugins->objects['styleUsernames'] = new styleUsernames();

/**
 * Standard MyBB info function
 * 
 */
function styleUsernames_info()
{
    global $lang;

    $lang->load("styleUsernames");
    
    $lang->styleUsernamesDesc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' . 
        '<input type="hidden" name="hosted_button_id" value="3BTVZBUG6TMFQ">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->styleUsernamesDesc;

    return Array(
        'name' => $lang->styleUsernamesName,
        'description' => $lang->styleUsernamesDesc,
        'website' => 'http://lukasztkacz.com',
        'author' => 'Lukasz "LukasAMD" Tkacz',
        'authorsite' => 'http://lukasztkacz.com',
        'version' => '1.7',
        'guid' => '6f0320c2e4b0f78792dd626a60ea33d1',
        'compatibility' => '16*'
    );
}

/**
 * Plugin Class
 */
class styleUsernames
{

    private $cache = array(
        'groups' => array(),
        'mods' => array(),
        'users' => array(),
    );

    /**
     * Constructor - add plugin hooks
     */
    public function __construct()
    {
        global $plugins;

        $plugins->hooks["global_end"][10]["su_getModerators"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->getModerators();'));
        $plugins->hooks["pre_output_page"][10]["su_parseUsernames"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->parseUsernames($arg);'));
        $plugins->hooks["build_forumbits_forum"][10]["su_buildForumbits"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->buildForumbits($arg);'));
        $plugins->hooks["forumdisplay_announcement"][10]["su_forumdisplayAnnouncement"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->forumdisplayAnnouncement();'));
        $plugins->hooks["forumdisplay_thread"][10]["su_forumdisplayThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->forumdisplayThread();'));
        $plugins->hooks["search_results_thread"][10]["su_searchThread"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->searchThread();'));
        $plugins->hooks["search_results_post"][10]["su_searchPost"] = array("function" => create_function('', 'global $plugins; $plugins->objects[\'styleUsernames\']->searchPost();'));
        $plugins->hooks["pre_output_page"][10]["su_pluginThanks"] = array("function" => create_function('&$arg', 'global $plugins; $plugins->objects[\'styleUsernames\']->pluginThanks($arg);'));
    }

    /**
     * Change moderators usernames to Style Usernames code and get their ids.
     */
    public function getModerators()
    {
        global $cache;

        if (empty($cache->cache['moderators']))
        {
            $cache->cache['moderators'] = $cache->read("moderators");
        }

        foreach ($cache->cache['moderators'] as $fid => $fdata)
        {
            if (isset($fdata['usergroups']))
            {
                foreach ($fdata['usergroups'] as $gid => $gdata)
                {
                    $cache->cache['moderators'][$fid]['usergroups'][$gid]['title'] = "#STYLE_USERNAMES_GID{$gid}#";
                    $this->cache['groups'][] = $gid;
                }
            }

            if (isset($fdata['users']))
            {
                foreach ($fdata['users'] as $uid => $udata)
                {
                    $cache->cache['moderators'][$fid]['users'][$uid]['username'] = "#STYLE_USERNAMES_UID{$uid}#";
                    $this->cache['users'][$uid] = $udata['username'];
                    $this->cache['mods'][] = $uid;
                }
            }
        }
    }

    /**
     * Parse all usernames using build-in uids cache - modify output code
     * 
     * @param string &$content Reference to output code    
     */
    public function parseUsernames(&$content)
    {
        global $db, $cache;

        // Parse users
        $this->cache['users'] = array_unique($this->cache['users']);
        $this->cache['mods'] = array_unique($this->cache['mods']);

        if (sizeof($this->cache['users']))
        {
            $result = $db->simple_select('users', 'uid, username, usergroup, displaygroup', 'uid IN (' . implode(',', array_keys($this->cache['users'])) . ')');
            while ($row = $db->fetch_array($result))
            {
                $username = format_name($row['username'], $row['usergroup'], $row['displaygroup']);
                $sign = "#STYLE_USERNAMES_UID{$row['uid']}#";

                // Delete old code - only for moderators (fix for images in usergroup style)
                if (in_array($row['uid'], $this->cache['mods']))
                {
                    $old_username = str_replace('{username}', $sign, $cache->cache['usergroups'][$row['usergroup']]['namestyle']);
                    if ($old_username != '')
                    {
                        $content = str_replace($old_username, $sign, $content);
                    }
                }

                $content = str_replace($sign, $username, $content);
                unset($this->cache['users'][$row['uid']]);
            }

            // Clean output for bad (non-isset) usernames
            if (sizeof($this->cache['users']))
            {
                foreach ($this->cache['users'] as $uid => $username)
                {
                    $sign = "#STYLE_USERNAMES_UID{$uid}#";
                    $content = str_replace($sign, $username, $content);
                }
            }
        }

        // Parse moderator groups
        $this->cache['groups'] = array_unique($this->cache['groups']);

        if (sizeof($this->cache['groups']))
        {
            foreach ($cache->cache['usergroups'] as $gid => $gdata)
            {
                if (!in_array($gid, $this->cache['groups']))
                {
                    continue;
                }
                $title = format_name($gdata['title'], $gid);
                $sign = "#STYLE_USERNAMES_GID{$gid}#";
                $content = str_replace($sign, $title, $content);
            }
        }
    }

    /**
     * Style usernames on forums list
     * 
     * @param array &$forum Reference to forum data
     */
    public function buildForumbits(&$forum)
    {
        $this->cache['users'][$forum['lastposteruid']] = $forum['lastposter'];
        $forum['lastposter'] = "#STYLE_USERNAMES_UID{$forum['lastposteruid']}#";
    }

    /**
     * Style usernames on announcements list
     */
    public function forumdisplayAnnouncement()
    {
        global $announcement;

        $this->cache['users'][$announcement['uid']] = $announcement['username'];
        $sign = "#STYLE_USERNAMES_UID{$announcement['uid']}#";
        $announcement['profilelink'] = str_replace($announcement['username'], $sign, $announcement['profilelink']);
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

        if ($thread['lastposteruid'] != 0)
        {
            $this->cache['users'][$thread['lastposteruid']] = $thread['lastposter'];
            $thread['lastposter'] = "#STYLE_USERNAMES_UID{$thread['lastposteruid']}#";
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
            $this->cache['users'][$thread['uid']] = $thread['username'];
            $sign = ">#STYLE_USERNAMES_UID{$thread['uid']}#<";
            $thread['profilelink'] = str_replace(">{$thread['username']}<", $sign, $thread['profilelink']);
        }

        if ($thread['lastposteruid'] != 0)
        {
            $this->cache['users'][$thread['lastposteruid']] = $thread['lastposter'];
            $sign = ">#STYLE_USERNAMES_UID{$thread['lastposteruid']}#<";
            $lastposterlink = str_replace(">{$thread['lastposter']}<", $sign, $lastposterlink);
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
    }
    
    /**
     * Say thanks to plugin author - paste link to author website.
     * If you didn't make donate, don't remove this code
     * otherwise you're breaking the license     
     */
    public function pluginThanks(&$content)
    {
        global $session, $lukasamd_thanks;
        
        if (!isset($lukasamd_thanks) && $session->is_spider)
        {
            $thx = '<div style="margin:auto; text-align:center;">This forum uses <a href="http://lukasztkacz.com">Lukasz Tkacz</a> MyBB addons.</div></body>';
            $content = str_replace('</body>', $thx, $content);
            $lukasamd_thanks = true;
        }
    }
    
}