<?php
class FOGCore extends FOGBase {
    /** attemptLogin($username,$password)
        Checks the login and returns the user or nothing if not valid/not exist.
     */
    public function attemptLogin($username,$password) {
        $User = current($this->getClass('UserManager')->find(array('name' => $username)));
        if ($User && $User->isValid() && $User->validate_pw($password)) return $User;
        return false;
    }
    /** stopScheduledTask($task)
        Stops the scheduled task.
     */
    public function stopScheduledTask($task) {
        $ScheduledTask = new ScheduledTask($task->get('id'));
        return $ScheduledTask->set('isActive',0)->save();
    }
    /** redirect($url = '')
        Redirect the page.
     */
    public function redirect($url = '') {
        if ($url == '') $url = $_SERVER['PHP_SELF'] . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : '');
        if (headers_sent()) printf('<meta http-equiv="refresh" content="0; url=%s">', $url);
        else {
            header('X-Content-Type-Options: nosniff');
            header('Strict-Transport-Security: max-age=16070400; includeSubDomains');
            header('X-XSS-Protection: 1; mode=block');
            header('X-Frame-Options: deny');
            header('Cache-Control: no-cache');
            header("Location: $url");
        }
        exit;
    }
    /** setMessage(,$txt, $data = array())
        Sets the message at the top of the screen (e.g. 14 Active Tasks Found)
     */
    public function setMessage($txt, $data = array()) {
        $_SESSION['FOG_MESSAGES'] = (!is_array($txt) ? array(vsprintf($txt, $data)) : $txt);
        return $this;
    }
    /** getMessage()
        Get's the current message in the store to display to the screen
     */
    public function getMessages() {
        print "<!-- FOG Variables -->\n";
        $cnt = 0;
        foreach ((array)$_SESSION['FOG_MESSAGES'] AS $message) {
            // Hook
            $this->HookManager->processEvent('MessageBox', array('data' => &$message));
            // Message Box
            print ($cnt++ > 0 ? "\t\t" : '').'<div class="fog-message-box">'.$message."</div>\n";
        }
        unset($_SESSION['FOG_MESSAGES']);
    }
    /** logHistory($string)
        Logs the actions to the database.
     */
    public function logHistory($string) {
        global $conn, $currentUser;
        $uname = "";
        if ($currentUser != null) $uname = $currentUser->get('name');
        $this->getClass('History')
            ->set('info',$string)
            ->set('createdBy',$uname)
            ->set('createdTime',$this->nice_date()->format('Y-m-d H:i:s'))
            ->set('ip',$_SERVER[REMOTE_ADDR])
            ->save();
    }
    /** getSetting($key)
        Get's global Setting Values
     */
    public function getSetting($key) {
        $Service = current($this->getClass('ServiceManager')->find(array('name' => $key)));
        return $Service && $Service->isValid() ? $Service->get('value') : '';
    }
    /** setSetting($key, $value)
        Set's a new default value.
     */
    public function setSetting($key, $value) {
        $ServMan = current($this->getClass('ServiceManager')->find(array('name' => $key)));
        if ($ServMan && $ServMan->isValid()) return $ServMan->set('value',$value)->save();
        return false;
    }
    /** getMACManufacturer($macprefix)
        Returns the Manufacturer of the prefix sent if the tables are loaded.
     */
    public function getMACManufacturer($macprefix) {
        $OUI = current($this->getClass('OUIManager')->find(array('prefix' => $macprefix)));
        return ($OUI && $OUI->isValid() ? $OUI->get('name') : $this->foglang['n/a']);
    }
    /** addUpdateMACLookupTable($macprefix,$strMan)
        Updates/add's MAC Manufacturers
     */
    public function addUpdateMACLookupTable($macprefix) {
        $this->clearMACLookupTable();
        foreach($macprefix AS $macpre => &$maker) $macArray[] = "('".$this->DB->sanitize($macpre)."','".$this->DB->sanitize($maker)."')";
        $sql = "INSERT INTO `oui` (`ouiMACPrefix`,`ouiMan`) VALUES ".implode((array)$macArray,',');
        return $this->DB->query($sql);
    }
    /** clearMACLookupTable()
        Clear's all entries in the table.
     */
    public function clearMACLookupTable() {return !$this->DB->query("TRUNCATE TABLE %s",$this->getClass('OUI')->databaseTable)->fetch()->get();}
    /** getMACLookupCount()
        returns the number of MAC's loaded.
     */
        public function getMACLookupCount() {return $this->getClass('OUIManager')->count();}
    /** resolveHostname($host)
        Returns the hostname.  Useful for Hostname dns translating for the server (e.g. fogserver instead of 127.0.0.1) in the address
        bar.
     */
        public function resolveHostname($host) {
            if (filter_var($host,FILTER_VALIDATE_IP)) $ip = $host;
            else $ip = gethostbyname($host);
            return $ip;
        }
    /** makeTempFilePath()
        creates the temporary file.
     */
    public function makeTempFilePath() {return tempnam(sys_get_temp_dir(), 'FOG');}
    /** wakeOnLAN($mac)
        Wakes systems up with the magic packet.
     */
        public function wakeOnLAN($mac) {return $this->FOGURLRequests->process(sprintf('http://%s%s?wakeonlan=%s', $this->getSetting('FOG_WOL_HOST'), $this->getSetting('FOG_WOL_PATH'), $mac),'GET');}
        // Blackout - 2:40 PM 25/05/2011
    /** SystemUptime()
        Returns the uptime of the server.
     */
        public function SystemUptime() {
            $data = trim(shell_exec('uptime'));
            $tmp = explode(' load average: ', $data);
            $load = end($tmp);
            $tmp = explode(' up ',$data);
            $tmp = explode(',', end($tmp));
            $uptime = $tmp;
            $uptime = (count($uptime) > 1 ? $uptime[0] . ', ' . $uptime[1] : 'uptime not found');
            return array('uptime' => $uptime, 'load' => $load);
        }
    /** clear_screen($outputdevice)
        Clears the screen for information.
     */
    public function clear_screen($outputdevice) {$this->out(chr(27)."[2J".chr(27)."[;H",$outputdevice);}
    /** wait_interface_ready($interface,$outputdevice)
        Waits for the network interface to be ready so services operate.
     */
        public function wait_interface_ready($interface,$outputdevice) {
            while (true) {
                $retarr = array();
                exec('netstat -inN',$retarr);
                array_shift($retarr);
                array_shift($retarr);
                foreach($retarr AS $line) {
                    $t = substr($line,0,strpos($line,' '));
                    if ($t == $interface) {
                        $this->out('Interface now ready..',$outputdevice);
                        break 2;
                    }
                }
                $this->out('Interface not ready, waiting..',$outputdevice);
                sleep(10);
            }
        }
    // The below functions are from the FOG Service Scripts Data writing and checking.
    /** out($sting, $device, $blLog=false,$blNewLine=true)
        prints the information to the service log files.
     */
    public function out($string,$device,$blLog=false,$blNewLine=true) {
        ($blNewLine ? $strOut = $string."\n" : null);
        if (!$hdl = fopen($device,'w')) return;
        if (fwrite($hdl,$strOut) === FALSE) return;
        fclose($hdl);
    }
    /** getDateTime()
        Returns the date format used at the start of each line in the service lines.
     */
    public function getDateTime() {return $this->nice_date()->format('m-d-y g:i:s a');}
    /** wlog($string, $path)
        Writes to the log file and clears if needed.
     */
        public function wlog($string, $path) {
            if (filesize($path) > LOGMAXSIZE) unlink($path);
            if (!$hdl = fopen($path,'a')) $this->out("\n * Error: Unable to open file: $path\n");
            if (fwrite($hdl,sprintf('[%s] %s%s',$this->getDateTime(),$string,"\n")) === FALSE) $this->out("\n * Error: Unable to write to file: $path\n");
        }
    /** getIPAddress()
        Gets the service server's IP address.
     */
    public function getIPAddress() {
        $output = array();
        exec("/sbin/ip addr | grep '[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}\.[0-9]\{1,3\}'| cut -d/ -f1 | awk '{print $2}'", $IPs, $retVal);
        foreach ($IPs AS $IP) {
            $IP = trim($IP);
            if ($IP != "127.0.0.1") {
                if (($bIp = ip2long($IP)) !== false) $output[] = $IP;
                $output[] = gethostbyaddr($IP);
            }
        }
        $output = array_values(array_unique((array)$output));
        return $output;
    }
    /** getBanner()
        Prints the FOG banner
     */
    public function getBanner() {
        $str  = "        ___           ___           ___      \n";
        $str .= "       /\  \         /\  \         /\  \     \n";
        $str .= "      /::\  \       /::\  \       /::\  \    \n";
        $str .= "     /:/\:\  \     /:/\:\  \     /:/\:\  \   \n";
        $str .= "    /::\-\:\  \   /:/  \:\  \   /:/  \:\  \  \n";
        $str .= "   /:/\:\ \:\__\ /:/__/ \:\__\ /:/__/_\:\__\ \n";
        $str .= "   \/__\:\ \/__/ \:\  \ /:/  / \:\  /\ \/__/ \n";
        $str .= "        \:\__\    \:\  /:/  /   \:\ \:\__\   \n";
        $str .= "         \/__/     \:\/:/  /     \:\/:/  /   \n";
        $str .= "                    \::/  /       \::/  /    \n";
        $str .= "                     \/__/         \/__/     \n";
        $str .= "\n";
        $str .= "  ###########################################\n";
        $str .= "  #     Free Computer Imaging Solution      #\n";
        $str .= "  #                                         #\n";
        $str .= "  #     Created by:                         #\n";
        $str .= "  #         Chuck Syperski                  #\n";
        $str .= "  #         Jian Zhang                      #\n";
        $str .= "  #         Tom Elliott                     #\n";
        $str .= "  #                                         #\n";
        $str .= "  #     GNU GPL Version 3                   #\n";
        $str .= "  ###########################################\n";
        $str .= "\n";
        return $str;
    }

    /** getHWInfo()
     * Returns the hardware information for hwinfo link on dashboard.
     * @return $data
     */
    public function getHWInfo() {
        $data['general'] = '@@general';
        $data['kernel'] = trim(php_uname('r'));
        $data['hostname'] = trim(php_uname('n'));
        $data['uptimeload'] = trim(shell_exec('uptime'));
        $data['cputype'] = trim(shell_exec("cat /proc/cpuinfo | head -n2 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucount'] = trim(shell_exec("grep '^processor' /proc/cpuinfo | tail -n 1 | awk '{print \$3+1}'"));
        $data['cpumodel'] = trim(shell_exec("cat /proc/cpuinfo | head -n5 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpuspeed'] = trim(shell_exec("cat /proc/cpuinfo | head -n8 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['cpucache'] = trim(shell_exec("cat /proc/cpuinfo | head -n9 | tail -n1 | cut -f2 -d: | sed 's| ||'"));
        $data['totmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$2 }'")));
        $data['usedmem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$3 }'")));
        $data['freemem'] = $this->formatByteSize(trim(shell_exec("free -b | head -n2 | tail -n1 | awk '{ print \$4 }'")));
        $data['filesys'] = '@@fs';
        $t = shell_exec('df | grep -vE "^Filesystem|shm"');
        $l = explode("\n",$t);
        foreach ($l AS $n) {
            if (preg_match("/(\d+) +(\d+) +(\d+) +\d+%/",$n,$matches)) {
                if (is_numeric($matches[1])) $hdtotal += $matches[1]*1024;
                if (is_numeric($matches[2])) $hdused += $matches[2]*1024;
            }
        }
        $data['totalspace'] = $this->formatByteSize($hdtotal);
        $data['usedspace'] = $this->formatByteSize($hdused);
        $data['nic'] = '@@nic';
        $NET = shell_exec('cat "/proc/net/dev"');
        $lines = explode("\n",$NET);
        foreach ($lines AS $line) {
            if (preg_match('/:/',$line)) {
                list($dev_name,$stats_list) = preg_split('/:/',$line,2);
                $stats = preg_split('/\s+/', trim($stats_list));
                $data[$dev_name] = trim($dev_name).'$$'.$stats[0].'$$'.$stats[8].'$$'.($stats[2]+$stats[10]).'$$'.($stats[3]+$stats[11]);
            }
        }
        $data['end'] = '@@end';
        return $data;
    }
    /**
     * track($list, $c = 0, $i = 0)
     * @param $list the data to bencode.
     * @param $c completed jobs (seeders)
     * @param $i incompleted jobs (leechers)
     * @return void
     * Will "return" but through throw/catch statement.
     */
    public function track($list, $c = 0, $i = 0) {
        if (is_string($list)) return 'd14:failure reason'.strlen($list).':'.$list.'e';
        $p = '';
        foreach((array)$list AS $d) {
            $peer_id = '';
            if (!$_REQUEST['no_peer_id']) $peer_id = '7:peer id'.strlen($this->hex2bin($d[2])).':'.$this->hex2bin($d[2]);
            $p .= 'd2:ip'.strlen($d[0]).':'.$d[0].$peer_id.'4:porti'.$d[1].'ee';
        }
        return 'd8:intervali'.$this->getSetting('FOG_TORRENT_INTERVAL').'e12:min intervali'.$this->getSetting('FOG_TORRENT_INTERVAL_MIN').'e8:completei'.$c.'e10:incompletei'.$i.'e5:peersl'.$p.'ee';
    }
    /**
     * valdata($g,$fixed_size=false)
     * Function simply checks if the required data is met and valid
     * Could use for other functions possibly too.
     * @param $g the request/get/post info to validate.
     * @return void
     * Sends info back to track.
     */
    public function valdata($g,$fixed_size=false) {
        try {
            if (!$_REQUEST[$g]) throw new Exception($this->track('Invalid request, missing data'));
            if (!is_string($_REQUEST[$g])) throw new Exception($this->track('Invalid request, unkown data type'));
            if ($fixed_size && strlen($_REQUEST[$g]) != 20) throw new Exception($this->track('Invalid request, length on fixed argument not correct'));
            if (strlen($_REQUEST[$g]) > 80) throw new Exception($this->track('Request too long'));
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
    public function setSessionEnv() {
        /** This allows the database concatination system based on number of hosts */
        $this->DB->query("SET SESSION group_concat_max_len=(1024 * {$_SESSION[HostCount]})")->fetch()->get();
        /** This below ensures the database is always MyISAM */
        $this->DB->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '".DATABASE_NAME."' AND ENGINE != 'MyISAM'");
        /** $tables just stores the tables to cycle through and change as needed */
        $tables = $this->DB->fetch(MYSQLI_NUM,'fetch_all')->get('TABLE_NAME');
        foreach ((array)$tables AS $table) $this->DB->query("ALTER TABLE `".DATABASE_NAME."`.`".array_shift($table)."` ENGINE=MyISAM");
        /** frees the memory of the $tables and $table values */
        unset($tables,$table);
        $_SESSION['theme'] = $this->getSetting('FOG_THEME');
        $_SESSION['theme'] = $_SESSION['theme'] ? $_SESSION['theme'] : 'default/fog.css';
        if (!file_exists(BASEPATH.'/css/'.$_SESSION['theme'])) $_SESSION['theme'] = 'default/fog.css';
        $_SESSION['imagelink'] = !preg_match('#/mobile/#i',$_SERVER['PHP_SELF']) ? 'css/'.($_SESSION['theme'] ? dirname($_SESSION['theme']) : 'default').'/images/' : 'css/images/';
        $_SESSION['PLUGSON'] = $this->getSetting('FOG_PLUGINSYS_ENABLED');
        $_SESSION['PluginsInstalled'] = $this->getActivePlugins();
        $_SESSION['FOG_VIEW_DEFAULT_SCREEN'] = $this->getSetting('FOG_VIEW_DEFAULT_SCREEN');
        $_SESSION['FOG_FTP_IMAGE_SIZE'] = $this->getSetting('FOG_FTP_IMAGE_SIZE');
        $_SESSION['Pending-Hosts'] = $this->getClass('HostManager')->count(array('pending' => 1));
        $_SESSION['Pending-MACs'] = $this->getClass('MACAddressAssociationManager')->count(array('pending' => 1));
        $_SESSION['DataReturn'] = $this->getSetting('FOG_DATA_RETURNED');
        $_SESSION['UserCount'] = $this->getClass('UserManager')->count();
        $_SESSION['HostCount'] = $this->getClass('HostManager')->count();
        $_SESSION['GroupCount'] = $this->getClass('GroupManager')->count();
        $_SESSION['ImageCount'] = $this->getClass('ImageManager')->count();
        $_SESSION['SnapinCount'] = $this->getClass('SnapinManager')->count();
        $_SESSION['PrinterCount'] = $this->getClass('PrinterManager')->count();
        $_SESSION['FOGPingActive'] = $this->getSetting('FOG_HOST_LOOKUP');
        // Set the memory limits
        $_SESSION['memory'] = $this->getSetting('FOG_MEMORY_LIMIT');
        ini_set('memory_limit',is_numeric($_SESSION['memory']) ? $_SESSION['memory'].'M' : ini_get('memory_limit'));
        $_SESSION['chunksize'] = 8192;
        $_SESSION['FOG_FORMAT_FLAG_IN_GUI'] = $this->getSetting('FOG_FORMAT_FLAG_IN_GUI');
        $_SESSION['FOG_SNAPINDIR'] = $this->getSetting('FOG_SNAPINDIR');
        $_SESSION['FOG_REPORT_DIR'] = $this->getSetting('FOG_REPORT_DIR');
        /** $TimeZone set the TimeZone based on the stored data */
        $_SESSION['TimeZone'] = (ini_get('date.timezone') ? ini_get('date.timezone') : $this->getSetting('FOG_TZ_INFO'));
        ini_set('max_input_vars',5000);
        ini_set('upload_max_filesize',$this->getSetting('FOG_MAX_UPLOADSIZE').'M');
        ini_set('post_max_size',$this->getSetting('FOG_POST_MAXSIZE').'M');
    }
    public function cleanupBadEntries() {
        $idFieldsToCleanup = array(
            'SnapinGroup',
            'Image',
            'Printer',
            'MACAddress',
            'Group',
            'Module',
            'MulticastSessions',
            'Snapin',
            'Printer',
        );
        foreach ($idFieldsToCleanup AS $class) $this->getClass($class.'AssociationManager')->destroy(array('id' => array('','NULL',0)));
    }
    public function associationCleanup() {
        $hostIDs = $this->getClass('HostManager')->find('','','','','','','','id');
        $HostIDs = $this->getClass('SnapinAssociationManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('SnapinAssociationManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('MACAddressAssociationManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('MACAddressAssociationManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('GroupAssociationManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('GroupAssociationManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('PrinterAssociationManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('PrinterAssociationManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('ModuleAssociationManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('ModuleAssociationManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('InventoryManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('InventoryManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('HostAutoLogoutManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('HostAutoLogoutManager')->destroy(array('id' => $HostIDs));
        $HostIDs = $this->getClass('HostScreenSettingsManager')->find(array('hostID' => $hostIDs),'','','','','',true,'id');
        $this->getClass('HostScreenSettingsManager')->destroy(array('id' => $HostIDs));
    }
}
