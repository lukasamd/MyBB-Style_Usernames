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

if (!defined('IN_MYBB')) {
    exit;
}

$plugins->add_hook('pre_output_page', array('styleUsernames', 'parseUsernames'));
$plugins->add_hook('global_end', array('styleUsernames', 'getModerators'));
$plugins->add_hook('build_forumbits_forum', array('styleUsernames', 'buildForumbits'));
$plugins->add_hook('forumdisplay_announcement', array('styleUsernames', 'forumdisplayAnnouncement'));
$plugins->add_hook('forumdisplay_thread', array('styleUsernames', 'forumdisplayThread'));
$plugins->add_hook('search_results_thread', array('styleUsernames', 'searchThread'));
$plugins->add_hook('search_results_post', array('styleUsernames', 'searchPost'));
$plugins->add_hook('private_message', array('styleUsernames', 'privateMessage'));
$plugins->add_hook('portal_announcement', array('styleUsernames', 'portalAnnouncement'));

/**
 * Standard MyBB info function
 *
 * @return array
 */
function styleUsernames_info()
{
    global $lang;

    $lang->load('styleUsernames');

    $lang->styleUsernamesDesc = '<form action="https://www.paypal.com/cgi-bin/webscr" method="post" style="float:right;">' .
        '<input type="hidden" name="cmd" value="_s-xclick">' .
        '<input type="hidden" name="hosted_button_id" value="3BTVZBUG6TMFQ">' .
        '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">' .
        '<img alt="" border="0" src="https://www.paypalobjects.com/pl_PL/i/scr/pixel.gif" width="1" height="1">' .
        '</form>' . $lang->styleUsernamesDesc;

    return array(
        'name' => $lang->styleUsernamesName,
        'description' => $lang->styleUsernamesDesc,
        'website' => 'https://tkacz.pro',
        'author' => 'Lukasz "LukasAMD" Tkacz',
        'authorsite' => 'https://tkacz.pro',
        'version' => '2.0.0',
        'guid' => '',
        'compatibility' => '18*',
        'codename' => 'style_usernames'
    );
}

/**
 * Styles selected usernames that MyBB 1.8 still renders without group styles.
 */
class styleUsernames
{
    private static $users = array();
    private static $userTokens = array();
    private static $guestTokens = array();
    private static $groups = array();
    private static $mods = array();
    private static $tokenSalt = '';
    private static $guestTokenCounter = 0;

    /**
     * Change moderators usernames to Style Usernames tokens and collect ids.
     */
    public static function getModerators(&$unused = null)
    {
        global $cache;

        if (empty($cache->cache['moderators']) || !is_array($cache->cache['moderators'])) {
            $moderators = $cache->read('moderators');
            $cache->cache['moderators'] = is_array($moderators) ? $moderators : array();
        }

        foreach ($cache->cache['moderators'] as $fid => $fdata) {
            if (!empty($fdata['usergroups']) && is_array($fdata['usergroups'])) {
                foreach ($fdata['usergroups'] as $gid => $gdata) {
                    $gid = (int)$gid;
                    if ($gid <= 0) {
                        continue;
                    }

                    $cache->cache['moderators'][$fid]['usergroups'][$gid]['title'] = self::groupToken($gid);
                    self::$groups[$gid] = $gid;
                }
            }

            if (!empty($fdata['users']) && is_array($fdata['users'])) {
                foreach ($fdata['users'] as $uid => $udata) {
                    $uid = (int)$uid;
                    if ($uid <= 0) {
                        continue;
                    }

                    $username = isset($udata['username']) ? $udata['username'] : '';
                    $cache->cache['moderators'][$fid]['users'][$uid]['username'] = self::userToken($uid, $username);
                    self::$mods[$uid] = $uid;
                }
            }
        }
    }

    /**
     * Parse all generated tokens after templates have been rendered.
     *
     * @param string &$content Reference to output code
     */
    public static function parseUsernames(&$content)
    {
        global $db;

        if (!is_string($content)) {
            return;
        }

        if (!empty(self::$users)) {
            $uids = array_map('intval', array_keys(self::$users));
            $uids = array_filter(array_unique($uids));

            if (!empty($uids)) {
                $query = $db->simple_select(
                    'users',
                    'uid, username, usergroup, displaygroup',
                    'uid IN (' . implode(',', $uids) . ')'
                );

                while ($row = $db->fetch_array($query)) {
                    $uid = (int)$row['uid'];
                    if (!isset(self::$userTokens[$uid])) {
                        continue;
                    }

                    $token = self::$userTokens[$uid];
                    $username = self::formatUsername($row['username'], $row['usergroup'], $row['displaygroup']);

                    if (isset(self::$mods[$uid])) {
                        $wrappedToken = format_name($token, (int)$row['usergroup'], (int)$row['displaygroup']);
                        if ($wrappedToken !== $token) {
                            $content = str_replace($wrappedToken, $token, $content);
                        }
                    }

                    $content = str_replace($token, $username, $content);
                    unset(self::$users[$uid]);
                }
            }

            foreach (self::$users as $uid => $username) {
                if (!isset(self::$userTokens[$uid])) {
                    continue;
                }

                $content = str_replace(self::$userTokens[$uid], htmlspecialchars_uni($username), $content);
            }
        }

        foreach (self::$guestTokens as $token => $username) {
            $content = str_replace($token, self::formatUsername($username, 1, 1), $content);
        }

        if (!empty(self::$groups)) {
            foreach (self::getUsergroups() as $gid => $gdata) {
                $gid = (int)$gid;
                if (!isset(self::$groups[$gid]) || !isset($gdata['title'])) {
                    continue;
                }

                $content = str_replace(self::groupToken($gid), self::formatUsername($gdata['title'], $gid), $content);
            }
        }
    }

    /**
     * Style usernames on forums list.
     *
     * @param array &$forum Reference to forum data
     * @return array
     */
    public static function buildForumbits(&$forum)
    {
        if (!is_array($forum)) {
            return $forum;
        }

        $lastposteruid = isset($forum['lastposteruid']) ? (int)$forum['lastposteruid'] : 0;
        $lastposter = isset($forum['lastposter']) ? $forum['lastposter'] : '';

        if ($lastposteruid > 0) {
            $forum['lastposter'] = self::userToken($lastposteruid, $lastposter);
        } elseif ($lastposter !== '') {
            $forum['lastposter'] = self::guestToken($lastposter);
        }

        return $forum;
    }

    /**
     * Style usernames on announcements list.
     */
    public static function forumdisplayAnnouncement(&$unused = null)
    {
        global $announcement;

        if (empty($announcement) || !is_array($announcement)) {
            return;
        }

        $uid = isset($announcement['uid']) ? (int)$announcement['uid'] : 0;
        if ($uid > 0) {
            $announcement['profilelink'] = build_profile_link(
                self::userToken($uid, isset($announcement['username']) ? $announcement['username'] : ''),
                $uid
            );
        }
    }

    /**
     * Style usernames on topics list.
     */
    public static function forumdisplayThread(&$unused = null)
    {
        global $thread;

        if (empty($thread) || !is_array($thread)) {
            return;
        }

        if (!empty($thread['username']) && !empty($thread['uid'])) {
            $thread['username'] = self::userToken((int)$thread['uid'], $thread['username']);
        } elseif (!empty($thread['threadusername'])) {
            $thread['username'] = self::guestToken($thread['threadusername']);
        }

        $lastposteruid = isset($thread['lastposteruid']) ? (int)$thread['lastposteruid'] : 0;
        $lastposter = isset($thread['lastposter']) ? $thread['lastposter'] : '';

        if ($lastposteruid > 0) {
            $thread['lastposter'] = self::userToken($lastposteruid, $lastposter);
        } elseif ($lastposter !== '') {
            $thread['lastposter'] = self::guestToken($lastposter);
        }
    }

    /**
     * Style usernames on topics list (search results).
     */
    public static function searchThread(&$unused = null)
    {
        global $thread, $lastposterlink;

        if (empty($thread) || !is_array($thread)) {
            return;
        }

        $uid = isset($thread['uid']) ? (int)$thread['uid'] : 0;
        if (!empty($thread['username']) && $uid > 0) {
            $thread['profilelink'] = build_profile_link(self::userToken($uid, $thread['username']), $uid);
        } elseif (!empty($thread['username'])) {
            $thread['profilelink'] = self::guestToken($thread['username']);
        }

        $lastposteruid = isset($thread['lastposteruid']) ? (int)$thread['lastposteruid'] : 0;
        $lastposter = isset($thread['lastposter']) ? $thread['lastposter'] : '';
        if ($lastposteruid > 0) {
            $lastposterlink = build_profile_link(self::userToken($lastposteruid, $lastposter), $lastposteruid);
        } elseif ($lastposter !== '') {
            $lastposterlink = self::guestToken($lastposter);
        }
    }

    /**
     * Style usernames on posts list (search results).
     */
    public static function searchPost(&$unused = null)
    {
        global $post;

        if (empty($post) || !is_array($post)) {
            return;
        }

        $uid = isset($post['uid']) ? (int)$post['uid'] : 0;
        if ($uid > 0) {
            $post['profilelink'] = build_profile_link(
                self::userToken($uid, isset($post['username']) ? $post['username'] : ''),
                $uid
            );
        } elseif (!empty($post['username'])) {
            $post['profilelink'] = self::guestToken($post['username']);
        }
    }

    /**
     * Style usernames on PM lists.
     */
    public static function privateMessage(&$unused = null)
    {
        global $tofromusername, $tofromuid;

        $uid = isset($tofromuid) ? (int)$tofromuid : 0;
        if ($uid > 0) {
            $tofromusername = build_profile_link(self::userToken($uid, $tofromusername), $uid);
        }
    }

    /**
     * Style usernames on portal announcements.
     */
    public static function portalAnnouncement(&$unused = null)
    {
        global $profilelink, $announcement;

        if (empty($announcement) || !is_array($announcement)) {
            return;
        }

        $uid = isset($announcement['uid']) ? (int)$announcement['uid'] : 0;
        if ($uid > 0) {
            $profilelink = build_profile_link(
                self::userToken($uid, isset($announcement['username']) ? $announcement['username'] : ''),
                $uid
            );
        }
    }

    private static function userToken($uid, $username = '')
    {
        $uid = (int)$uid;
        self::$users[$uid] = $username;

        if (!isset(self::$userTokens[$uid])) {
            self::$userTokens[$uid] = self::tokenPrefix() . '_USER_' . $uid . '_';
        }

        return self::$userTokens[$uid];
    }

    private static function guestToken($username)
    {
        $token = self::tokenPrefix() . '_GUEST_' . (++self::$guestTokenCounter) . '_';
        self::$guestTokens[$token] = $username;

        return $token;
    }

    private static function groupToken($gid)
    {
        return self::tokenPrefix() . '_GROUP_' . (int)$gid . '_';
    }

    private static function tokenPrefix()
    {
        if (self::$tokenSalt === '') {
            self::$tokenSalt = 'STYLEUSERNAMES_' . md5(uniqid('', true));
        }

        return self::$tokenSalt;
    }

    private static function formatUsername($username, $usergroup, $displaygroup = 0)
    {
        return format_name(htmlspecialchars_uni($username), (int)$usergroup, (int)$displaygroup);
    }

    private static function getUsergroups()
    {
        global $cache;

        if (empty($cache->cache['usergroups']) || !is_array($cache->cache['usergroups'])) {
            $usergroups = $cache->read('usergroups');
            $cache->cache['usergroups'] = is_array($usergroups) ? $usergroups : array();
        }

        return $cache->cache['usergroups'];
    }
}
