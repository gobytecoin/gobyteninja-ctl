<?php

/*
    This file is part of GoByte Ninja.
    https://github.com/gobytecoin/gobyteninja-ctl

    GoByte Ninja is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    GoByte Ninja is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with GoByte Ninja.  If not, see <http://www.gnu.org/licenses/>.

 */

if (!defined('DMN_SCRIPT') || !defined('DMN_CONFIG') || (DMN_SCRIPT !== true) || (DMN_CONFIG !== true)) {
  die('Not executable');
}

DEFINE('DMN_VERSION','2.9.10');

DEFINE('GOVERNANCE_VOTES_TYPES',array('yes','no','abstain','none'));

function dmnpidcmp($a, $b)
{
    return strcmp($a['uname'],$b['uname']);
}

function dmn_finduname($dmnpid,$uname) {

  $res = false;
  $x = 0;
  while (($x < count($dmnpid)) && (!$res)) {
    if ($dmnpid[$x]['uname'] == $uname) {
      $res = true;
    }
    $x++;
  }
  return $res;
}

function dmn_getcountry($mnip,&$countrycode) {

  $mnipalone = substr($mnip,0,strpos($mnip,":"));
  $res = geoip_country_name_by_name($mnipalone);
  $countrycode = strtolower(geoip_country_code_by_name($mnipalone));
  return $res;

}

function dmn_getip($pid,$uname) {

  $res = false;
  exec('netstat -ntpl | grep "tcp  " | egrep ":([0-9]{1,4}|[1-5][0-9]{4}|6[0-4][0-9]{3}|65[0-4][0-9]{2}|655[0-2][0-9]|6553[0-5])" | grep "'.$pid.'/gobyted" | grep -v 127.',$output,$retval);
//  exec('netstat -ntpl | grep "tcp  " | egrep ":(1)?12455" | grep "'.$pid.'/darkcoind\|'.$pid.'/gobyted"',$output,$retval);
  if (isset($output[0])) {
    if (preg_match("/tcp        0      0 (\d*\.\d*.\d*.\d*:\d*)/", $output[0], $output_array) == 1) {
      $res = $output_array[1];
    }
  }
  return $res;

}

function dmn_getpayout($mncount,$difficulty) {

  $res = 0.0;
  if ($mncount !== false) {
    $res = round((2222222.0 / (pow(($difficulty+2600.0)/9.0,2.0))),0);
    if ($res < 5.0) {
      $res = 5.0;
    }
    if ($res > 25.0) {
      $res = 25.0;
    }
    $res = ($res*576.0*0.2)/$mncount;
  }
  return $res;

}

// Retrieve the PIDs for the hub nodes
function dmn_getpids($nodes,$isstatus = false,$istestnet) {
  if ($isstatus) {
    $semfnam = sprintf(DMN_CTLSTATUSAUTO_SEMAPHORE,$istestnet);
    if (file_exists($semfnam) && (posix_getpgid(intval(file_get_contents($semfnam))) !== false) ) {
      xecho("Already running (PID ".sprintf('%d',file_get_contents($semfnam)).")\n");
      die(10);
    }
    file_put_contents($semfnam,sprintf('%s',getmypid()));
  }

  $dmnpid = array();

  foreach($nodes as $uname => $node) {
    if (intval($node["NodeTestNet"]) == $istestnet) {
        if (is_dir(DMN_PID_PATH . $uname)) {
            $conf = new GoByteConfig($uname);
            if ($conf->isConfigLoaded()) {
                if ($node['NodeTestNet'] != $conf->getconfig('testnet')) {
                    xecho("$uname: Configuration inconsistency (testnet/" . $node['NodeTestNet'] . "/" . $conf->getconfig('testnet') . ")\n");
                }
                #if ($node['NodeEnabled'] != $conf->getmnctlconfig('enable')) {
                #    xecho("$uname: Configuration inconsistency (enable/" . $node['NodeEnabled'] . "/" . $conf->getmnctlconfig('enable') . ")\n");
                #}
                $pid = dmn_getpid($uname, ($conf->getconfig('testnet') == '1'));
                $dmnpiditem = array('pid' => $pid,
                    'uname' => $uname,
                    'conf' => $conf,
                    'type' => $node['NodeType'],
                    'enabled' => ($node['NodeEnabled'] == 1),
                    'testnet' => ($node['NodeTestNet'] == 1),
                    'gobyted' => $node['VersionPath'],
                    'currentbin' => '',
                    'keeprunning' => ($node['KeepRunning'] == 1),
                    'keepuptodate' => ($node['KeepUpToDate'] == 1),
                    'versionraw' => $node['VersionRaw'],
                    'versiondisplay' => $node['VersionDisplay'],
                    'versionhandling' => $node['VersionHandling']);
                if ($pid !== false) {
                    if (file_exists('/proc/' . $pid . '/exe')) {
                        $currentbin = readlink('/proc/' . $pid . '/exe');
                        $dmnpiditem['currentbin'] = $currentbin;
                        if ($currentbin != $node['VersionPath']) {
                            xecho("$uname: Binary mismatch ($currentbin != " . $node['VersionPath'] . ")");
                            /*              if ($dmnpiditem['keepuptodate']) {
                                            echo " [Restarting to fix]\n";
                                            dmn_startstop(array($dmnpiditem),"restart",($node['NodeTestNet'] == 1),$node['NodeType']);
                                            sleep(3);
                                            $pid = dmn_getpid($uname,($conf->getconfig('testnet') == '1'));
                                            $dmnpiditem['pid'] = $pid;
                                            if (($pid !== false) && (file_exists('/proc/'.$pid.'/exe'))) {
                                              $currentbin = readlink('/proc/'.$pid.'/exe');
                                              $dmnpiditem['currentbin'] = $currentbin;
                                              if ($currentbin != $node['VersionPath']) {
                                                xecho("$uname: Binary mismatch ($currentbin != ".$node['VersionPath'].") [Restart failed, need admin]\n");
                                              }
                                            }
                                          }
                                          else {  */
                            echo " [Restart to fix]\n";
//              }
                        }
                    } else {
                        xecho("$uname: process ID $pid has no binary information (crashed?)\n");
                    }
                } else {
                    xecho("$uname: process ID not found\n");
                }
                $dmnpid[] = $dmnpiditem;
            }
        }
    }
  }

  usort($dmnpid,"dmnpidcmp");

  return $dmnpid;

}

function dmn_getstatus($gobytedinfo,$blockhash) {

  $res = array('version' => false,
               'protocol' => false,
               'blocks' => 0,
               'connections' => 0,
               'difficulty' => false,
               'encryptedwallet' => false,
               'blockhash' => $blockhash,
               'testnet' => 0);

  if ($gobytedinfo !== false and is_array($gobytedinfo)) {
    if (array_key_exists('version',$gobytedinfo)) {
      $res['version'] = $gobytedinfo['version'];
    }
    if (array_key_exists('protocolversion',$gobytedinfo)) {
      $res['protocol'] = $gobytedinfo['protocolversion'];
    }
    if (array_key_exists('difficulty',$gobytedinfo)) {
      $res['difficulty'] = $gobytedinfo['difficulty'];
    }
    if (array_key_exists('blocks',$gobytedinfo)) {
      $res['blocks'] = $gobytedinfo['blocks'];
    }
    if (array_key_exists('connections',$gobytedinfo)) {
      $res['connections'] = $gobytedinfo['connections'];
    }
    if (array_key_exists('testnet',$gobytedinfo)
     && $gobytedinfo['testnet']) {
      $res['testnet'] = 1;
    }
    $res['encryptedwallet'] = array_key_exists('unlocked_until',$gobytedinfo);
  }
  return $res;

}

// Execute RPC commands
function dmn_ctlrpc(&$commands) {

  $cip = 0;
  $param = '';
  $descriptorspec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "a")
  );
  $threads = array();
  $pipes = array();
  $thid = 0;
  $commandsdone = 0;
  $done = 0;
  $lastdonetime = time();
  $lastdone = -1;
  $inittime = microtime(true);
  $nbpad = strlen(count($commands));
  $nbok = 0;
  $nberr = 0;

  xecho("Executing ".count($commands)." RPC commands (using ".DMN_THREADS_MAX." threads):\n");

  while ($done != count($commands)) {

    // Check if finished threads
    // If finished set the status of the command to 1 "Almost done"
    $oldthreads = $threads;
    $threads = array();
    foreach($oldthreads as $thread) {
      $info = proc_get_status($thread['res']);
      if (!$info['running']) {
        $cid = $thread['cid'];
        $commands[$cid]['status'] = 1;
        fclose($pipes[$cid][0]);
        $output = stream_get_contents($pipes[$cid][1]);
        if ($info['exitcode'] != 0) {
          $commands[$cid]['result'] = $output;
          $commands[$cid]['status'] = -1;
          $nberr++;
        }
        else {
          $commands[$cid]['status'] = 2;
          $nbok++;
        }
        fclose($pipes[$cid][1]);
        fclose($pipes[$cid][2]);
        proc_close($thread['res']);
        $done++;
      }
      else {
        $threads[] = $thread;
      }
    }

    // Fill up free threads with all possible commands
    // Execute the command in a thread
    while ((count($threads) < DMN_THREADS_MAX) && ($commandsdone < count($commands))) {
      $pipes[$commandsdone] = array();
      if (array_key_exists("timeout",$commands[$commandsdone])) {
        $timeout = $commands[$commandsdone]["timeout"];
      }
      else {
        $timeout = 10;
      }
      $thres[$commandsdone] = proc_open('timeout '.$timeout.' '.DMN_DIR.'/dmnctlrpc '.$commands[$commandsdone]['cmd'].' '.$commands[$commandsdone]['file'],$descriptorspec,$pipes[$commandsdone]);
      if (is_resource($thres[$commandsdone])) {
        $threads[] = array('cid' => $commandsdone, 'res' => $thres[$commandsdone]);
        $commandsdone++;
      }
    }
    if (($lastdone != $done) && (time() > $lastdonetime)) {
      xecho(" (".str_pad(round(($done/count($commands))*100,0),3," ",STR_PAD_LEFT)."% - ".str_pad($done,$nbpad," ",STR_PAD_LEFT)."/".count($commands).") In progress...\n");
      $lastdone = $done;
      $lastdonetime = time();
    }
    // Do a 100ms pause
    usleep(100000);
  }

  xecho(" (100% - ".count($commands)."/".count($commands).") Done in ".round(microtime(true)-$inittime,3)." seconds [$nbok sucessfully/$nberr with errors]\n");

}

// Execute start-stop command multi-threaded
function dmn_ctlstartstop(&$commands) {

  $descriptorspec = array(
      0 => array("pipe", "r"),
      1 => array("pipe", "w"),
      2 => array("pipe", "a")
  );
  $threads = array();
  $pipes = array();
  $commandsdone = 0;
  $done = 0;
  $lastdonetime = time();
  $lastdone = -1;
  $inittime = microtime(true);
  $nbpad = strlen(count($commands));
  $nbok = 0;
  $nberr = 0;

  xecho("Executing ".count($commands)." start-stop commands (using ".DMN_THREADS_MAX." threads):\n");

  while ($done != count($commands)) {

    // Check if finished threads
    // If finished set the status of the command to 1 "Almost done"
    $oldthreads = $threads;
    $threads = array();
    foreach($oldthreads as $thread) {
      $info = proc_get_status($thread['res']);
      if (!$info['running']) {
        $cid = $thread['cid'];
        $commands[$cid]['status'] = 1;
        fclose($pipes[$cid][0]);
        $output = stream_get_contents($pipes[$cid][1]);
        $commands[$cid]['output'] = $output;
        $commands[$cid]['exitcode'] = $info['exitcode'];
        if ($info['exitcode'] != 0) {
          $commands[$cid]['status'] = -1;
          $nberr++;
        }
        else {
          $commands[$cid]['status'] = 2;
          $nbok++;
        }
        fclose($pipes[$cid][1]);
        fclose($pipes[$cid][2]);
        proc_close($thread['res']);
        $done++;
      }
      else {
        $threads[] = $thread;
      }
    }

    // Fill up free threads with all possible commands
    // Execute the command in a thread
    while ((count($threads) < DMN_THREADS_MAX) && ($commandsdone < count($commands))) {
      $pipes[$commandsdone] = array();
      $thres[$commandsdone] = proc_open(DMN_DIR.'/dmnctlstartstopdaemon '.$commands[$commandsdone]['cmd'],$descriptorspec,$pipes[$commandsdone]);
      if (is_resource($thres[$commandsdone])) {
        $threads[] = array('cid' => $commandsdone, 'res' => $thres[$commandsdone]);
        $commandsdone++;
      }
    }
    if (($lastdone != $done) && (time() > $lastdonetime)) {
      xecho(" (".str_pad(round(($done/count($commands))*100,0),3," ",STR_PAD_LEFT)."% - ".str_pad($done,$nbpad," ",STR_PAD_LEFT)."/".count($commands).") In progress...\n");
      $lastdone = $done;
      $lastdonetime = time();
    }
    // Do a 100ms pause
    usleep(100000);
  }

  xecho(" (100% - ".count($commands)."/".count($commands).") Done in ".round(microtime(true)-$inittime,3)." seconds [$nbok sucessfully/$nberr with errors]\n");

}

//#############################################################################
//#############################################################################
//
//                             ACTIONS FUNCTIONS
//
//#############################################################################
//#############################################################################

// Show usage of the script
function dmn_help($exename)
{

  $exename = basename($exename);
  xecho("Usage: $exename action [option1] [option2] [..] [optionX]\n");
  xecho("\n");
  xecho("CONFIGURATION:\n");
  xecho("==============\n");
  xecho("Action         Description                      Expected parameters\n");
  xecho("-------------- -------------------------------- --------------------------------------------------------------\n");
  xecho("create         Create a monitoring node (user)  option1 = external IP to use\n");
  xecho("disable        Disable monitoring node(s)       List of nodes names (ex: dnmon03 dnmon04)\n");
  xecho("enable         Enable monitoring node(s)        List of nodes names (ex: dnmon03 dnmon04)\n");
  xecho("version        Create a new gobyted version       option1 = binary path\n");
  xecho("                                                option2 = display string\n");
  xecho("                                                option3 = testnet only (1 or 0)\n");
  xecho("                                                option4 = enabled (1 or 0)\n");
  xecho("\n");
  xecho("INTERACTION:\n");
  xecho("============\n");
  xecho("Action         Description                      Expected parameters\n");
  xecho("-------------- -------------------------------- --------------------------------------------------------------\n");
  xecho("start          Starts nodes                     option1 = testnet|mainnet, option2 = all|normal|block\n");
  xecho("status         Retrieve monitoring status       None\n");
  xecho("restart        Restarts nodes                   option1 = testnet|mainnet, option2 = all|normal|block\n");
  xecho("stop           Stop nodes                       option1 = testnet|mainnet, option2 = all|normal|block\n");
  xecho("\n");

}

// Create a new gobyted version in the database usable by nodes
function dmn_version_create($versionpath, $versiondisplay, $testnet, $enabled) {

  xecho("Retrieving raw version number from binary: ");
  $versionraw = dmn_gobytedversion($versionpath);
  if ($versionraw !== false) {
    echo "OK ($versionraw)\n";
    chmod($versionpath,0755);
    xecho("Retrieving binary information: ");
    $versionsize = filesize($versionpath);
    echo $versionsize." bytes... ";
    $versionhash = sha1(file_get_contents($versionpath));
    echo "OK (SHA1=$versionhash)\n";

    xecho("Detecting versionhandling parameter: ");

    list($versionmajor,$versionminor,$versionpatch,$versionbuild) = explode(".",$versionraw);
    $versionmajor = intval($versionmajor);
    $versionminor = intval($versionminor);
    $versionpatch = intval($versionpatch);
    $versionbuild = intval($versionbuild);

    echo "Major=$versionmajor Minor=$versionminor Patch=$versionpatch Build=$versionbuild";

    if ($versionmajor >= 0) {
      if ($versionminor >= 16) {
        $versionhandling = 7;
      }
      elseif ($versionminor >= 13) {
        $versionhandling = 6;
      }
      elseif ($versionminor == 12) {
        if ($versionpatch == 3) {
          $versionhandling = 5;
        }
        elseif ($versionpatch >= 1) {
          $versionhandling = 4;
        }
        else {
          $versionhandling = 3;
        }
      }
      else {
        $versionhandling = 2;
      }
    }

    echo " => VersionHandling=$versionhandling !\n";

    xecho("Submitting new version to webservice: ");
    $payload = array('VersionPath' => $versionpath,
        'VersionRaw' => $versionraw,
        'VersionDisplay' => $versiondisplay,
        'VersionTestnet' => $testnet,
        'VersionEnabled' => $enabled,
        'VersionURL' => '',
        'VersionHash' => $versionhash,
        'VersionSize' => $versionsize,
        'VersionHandling' => $versionhandling);
    $content = dmn_cmd_post('/versions',$payload,$response);
    if (strlen($content) > 0) {
      $content = json_decode($content,true);
      if (($response['http_code'] >= 200) && ($response['http_code'] <= 299)) {
        echo "Success (".$content['data']['VersionId'].")\n";
      }
      else {
        echo "Error (".$response['http_code'].": ".print_r($content['messages'],true).")\n";
      }
    }
    else {
      echo "Error (empty result) [HTTP CODE ".$response['http_code']."]\n";
    }
  }
  else {
    echo "8\n\n";
    echo "Error\n";
    die(1);
  }

}

// Create a new GoByte Monitoring node user, prepare folder and configuration
// TODO Broken
function dmn_create($dmnpid,$ip,$forcename = '') {

  if ($forcename == '') {
    echo "Forcing $forcename: ";
    $newnum = intval(substr($dmnpid[count($dmnpid)-1]['uname'],5,2))+1;
    $newuname = DMNPIDPREFIX.str_pad($newnum,2,'0',STR_PAD_LEFT);
  }
  else {
    $newuname = $forcename;
    $newnum = intval(substr($forcename,-2));
  }
  if (DMNTESTNET === true) {
    $testinfo = ' Testnet';
  }
  else {
    $testinfo = '';
  }
  echo "Creating $newuname: ";
  exec('useradd -m -c "GoByte Ninja $testinfo Monitoring node #'.$newnum.'" -U -s /bin/false -p '.randomPassword(128).' '.$newuname.' 1>/dev/null 2>/dev/null',$output,$retval);
  if ($retval != 0) {
    echo "Already exists!\n";
    if ($forcename == '') {
      die;
    }
  }
  else {
    echo "retval=$retval\n";
  }
  echo "Generating gobyte.conf";
  mkdir("/home/$newuname/.gobytecore");
  touch("/home/$newuname/.gobytecore/gobyte.conf");
  chmod("/home/$newuname/.gobytecore",0700);
  chmod("/home/$newuname/.gobytecore/gobyte.conf",0600);
  $conflist = array('server=1',
         'rpcuser='.$newuname.'rpc',
         'rpcpassword='.randomPassword(128),
         'alertnotify=echo %s | mail -s "GoByte MasterNode #'.str_pad($newnum,2,'0',STR_PAD_LEFT).' Alert" somebody@mowhere.blackhole',
         'rpcallowip=127.0.0.1',
         "bind=$ip",
         'rpcport='.(intval($newnum)+DMNCTLRPCPORTVAL).'998',
         'masternode=0',
         "externalip=$ip",
         '#mnctlcfg#enable=1');
  if (DMNTESTNET === true) {
    $conflist[] = 'testnet=1';
  }

  $gobyteconf = implode("\n",$conflist);
  file_put_contents("/home/$newuname/.gobytecore/gobyte.conf",$gobyteconf);
  echo "OK\n";
  echo "Setting ACL";
  if (file_exists("/home/$newuname/.bash_history")) {
    chmod("/home/$newuname/.bash_history",0600);
  }
  chmod("/home/$newuname/.bashrc",0600);
  chmod("/home/$newuname/.profile",0600);
  chmod("/home/$newuname/.bash_logout",0600);
  chmod("/home/$newuname/",0700);
  chown("/home/$newuname/.gobytecore/",$newuname);
  chgrp("/home/$newuname/.gobytecore/",$newuname);
  chown("/home/$newuname/.gobytecore/gobyte.conf",$newuname);
  chgrp("/home/$newuname/.gobytecore/gobyte.conf",$newuname);
  echo "OK\n";
  echo "Add to /etc/network/interfaces\n";
  echo "        post-up /sbin/ifconfig eth0:$newnum $ip netmask 255.255.255.255 broadcast $ip\n";
  echo "        post-down /sbin/ifconfig eth0:$newnum down\n";

}

// Set the enable flag to 0 in gobyte.conf to disable the Masternode
function dmn_disable($dmnpid,$dmntodisable) {
  foreach ($dmntodisable as $uname) {
    echo "Disabling $uname: ";
    if (dmn_finduname($dmnpid,$uname)) {
      $conf = new GoByteConfig($uname);
      if (($conf->getmnctlconfig('enable') == 0) && ($conf->getmnctlconfig('enable') !== false)) {
        echo "Already disabled";
      }
      else {
        $conf->setmnctlconfig('enable',0);
        if ($conf->saveconfig() !== false) {
          echo "Done";
        }
        else {
          echo "Failed";
        }
      }
    }
    else {
      echo "Unknown GoByte MasterNode";
    }
    echo "\n";
  }
}

// Set the enable flag to 1 in gobyte.conf to enable the Masternode
function dmn_enable($dmnpid,$dmntoenable) {
  foreach ($dmntoenable as $uname) {
    echo "Enabling $uname: ";
    if (dmn_finduname($dmnpid,$uname)) {
      $conf = new GoByteConfig($uname);
      if ($conf->getmnctlconfig('enable') == 1) {
        echo "Already enabled";
      }
      else {
        $conf->setmnctlconfig('enable',1);
        if ($conf->saveconfig() !== false) {
          echo "Done";
        }
        else {
          echo "Failed";
        }
      }
    }
    else {
      echo "Unknown GoByte MasterNode";
    }
    echo "\n";
  }
}

// Start/Stop/Restart nodes
// $todo can be "start", "stop" or "restart"
// If $testnet is true then only start testnet (else start mainnet)
// $nodetype can be "p2pool" or "masternode"
function dmn_startstop($dmnpid,$todo,$testnet = false,$nodetype = 'masternode',$withreindex = false) {

  $nodes = array();
  foreach($dmnpid as $node) {
    if (($node['testnet'] == $testnet)
     && ($node['type'] == $nodetype)
     && ($node['enabled'])) {
      $nodes[] = $node;
    }
  }

  if ($todo == 'start') {
    xecho("Starting ");
  }
  elseif ($todo == 'stop') {
    xecho("Stopping ");
  }
  elseif ($todo == 'restart') {
    xecho("Restarting ");
  }
  else {
    xecho("Unknown command $todo. Terminated.\n");
    die();
  }

  $extra = "";
  if ($withreindex) {
    echo "with -reindex ";
    $extra = " -reindex";
  }
  echo count($nodes)." nodes:\n";

  $commands = array();
  foreach($nodes as $nodenum => $node) {
    $uname = $node['uname'];
    $commands[] = array("status" => 0,
                        "nodenum" => $nodenum,
                        "cmd" => "$uname $todo ".$node['gobyted'].$extra,
                        "exitcode" => -1,
                        "output" => '');
  }
  dmn_ctlstartstop($commands);

  foreach($commands as $command) {
    echo $command['output'];
  }

}

// Start all KeepRunning nodes
// If $testnet is true then only start testnet (else start mainnet)
function dmn_startkeeprunning($dmnpid) {

  $nodes = array();
  foreach($dmnpid as $node) {
    if ($node['keeprunning']) {
      $nodes[] = $node;
    }
  }

  xecho("Keep Running ".count($nodes)." nodes:\n");

  $commands = array();
  foreach($nodes as $nodenum => $node) {
    $uname = $node['uname'];
    $commands[] = array("status" => 0,
        "nodenum" => $nodenum,
        "cmd" => "$uname start ".$node['gobyted'],
        "exitcode" => -1,
        "output" => '');
  }
  dmn_ctlstartstop($commands);

  foreach($commands as $command) {
    echo $command['output'];
  }

}

// Restart frozen nodes
function dmn_restartfrozen($dmnpid) {

  xecho("Dealing with ");
  echo count($dmnpid)." frozen nodes:\n";

  $commands = array();
  $commands2 = array();
  foreach($dmnpid as $nodenum => $node) {
    $uname = $node['uname'];
    if (file_exists("/tmp/dmnctl-NR-$uname-counter")) {
      $counter = intval(file_get_contents("/tmp/dmnctl-NR-$uname-counter"));
      $counter++;
    }
    else {
      $counter = 1;
    }
    xechoToFile(DMN_NRCOUNTLOG,"Unresponsive ".$uname." counter ".$counter);
    if ($node["testnet"]) {
      $maxcount = DMN_NRCOUNT_TEST;
    }
    else {
      $maxcount = DMN_NRCOUNT;
    }
    if ($counter >= $maxcount) {
      unlink("/tmp/dmnctl-NR-$uname-counter",$counter);
      $commands[] = array("status" => 0,
          "nodenum" => $nodenum,
          "cmd" => "$uname stop " . $node['gobyted'],
          "exitcode" => -1,
          "output" => '');
      if ($node["keeprunning"]) {
        $commands2[] = array("status" => 0,
            "nodenum" => $nodenum,
            "cmd" => "$uname start " . $node['gobyted'],
            "exitcode" => -1,
            "output" => '');
        xechoToFile(DMN_NRCOUNTLOG,"Restarting unresponsive ".$uname);
      }
      else {
        xechoToFile(DMN_NRCOUNTLOG,"Stopping unresponsive ".$uname);
      }
    }
    else {
      file_put_contents("/tmp/dmnctl-NR-$uname-counter",$counter);
    }
  }
  dmn_ctlstartstop($commands);
  foreach($commands as $command) {
    echo $command['output'];
  }
  if (count($commands2) > 0) {
    dmn_ctlstartstop($commands2);
    foreach ($commands2 as $command) {
      echo $command['output'];
    }
  }

}

// Display masternode status and submit statistics to private API
function dmn_status($dmnpid,$istestnet) {

  $mninfolast = array();

  $mnlistfinal = array();
  $mnlist2final = array();
  $mnlastseen = array();
  $mnactivesince = array();
  $mnpubkeylistfinal = array();
  $difficultyfinal = 0;
  $daemonactive = array();
  $protocolinfo = array();
  $curprotocol = 0;
  $oldprotocol = 99999;
  $mnstatusexvalues = array('ENABLED','EXPIRED','VIN_SPENT','REMOVE','POS_ERROR','','PRE_ENABLED','WATCHDOG_EXPIRED','NEW_START_REQUIRED','UPDATE_REQUIRED','POSE_BAN','OUTPOINT_SPENT','SENTINEL_PING_EXPIRED', 'POSE_BANNED');

  $wsstatus = array();

  $netstr = "main";
  if ($istestnet == 1) {
    $netstr = "test";
  }
  $netstr.="net";

  xecho('Retrieving status for '.count($dmnpid)." $netstr nodes\n");

  if (!is_dir("/dev/shm/dmnctl")) {
    if (!mkdir("/dev/shm/dmnctl")) {
      echo "Failed to create directory.\n";
      die(100);
    }
  }

  $tmpdate = date('YmdHis');
  $commands = array();

  // First check the pid and getinfo for all nodes
  foreach($dmnpid as $dmnnum => $dmnpidinfo) {
    $uname = $dmnpidinfo['uname'];
    $dmnpid[$dmnnum]['pidstatus'] = dmn_checkpid($dmnpidinfo['pid']);
    if (($dmnpid[$dmnnum]['pidstatus']) && ($dmnpidinfo['currentbin'] != '')) {
      if ($dmnpidinfo['versionhandling'] >= 7) {
        $commands[] = array("status" => 0,
          "dmnnum" => $dmnnum,
          "datatype" => "info1",
          "cmd" => "$uname getnetworkinfo",
          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getnetworkinfo.json");
        $commands[] = array("status" => 0,
          "dmnnum" => $dmnnum,
          "datatype" => "info2",
          "cmd" => "$uname getblockchaininfo",
          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getblockchaininfo.json");
      }
      else {
        $commands[] = array("status" => 0,
          "dmnnum" => $dmnnum,
          "datatype" => "info",
          "cmd" => "$uname getinfo",
          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getinfo.json");
      }
    }
  }

  foreach($dmnpid as $dmnnum => $dmnpidinfo) {
    $uname = $dmnpidinfo['uname'];
    // Only vh 3+
    if (($dmnpidinfo['pidstatus']) && ($dmnpidinfo['currentbin'] != '') && ($dmnpidinfo['versionhandling'] >= 3) && ($dmnpidinfo['type'] != 'p2pool')) {
      // If we are in v12.3+ (vh=5+) we use the new JSON output (faster and easier)
      if ($dmnpidinfo['versionhandling'] >= 5) {
           $commands[] = array("status" => 0,
               "dmnnum" => $dmnnum,
               "datatype" => "mnlistfull",
               "cmd" => $uname . ' "masternodelist json"',
               "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list.json");
       }
       else {
           $commands[] = array("status" => 0,
               "dmnnum" => $dmnnum,
               "datatype" => "mnlistfull",
               "cmd" => $uname . ' "masternode list full"',
               "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list.json");
       }
      // v12.1 (vh=4)
      if ($dmnpidinfo['versionhandling'] >= 4) {
        $commands[] = array("status" => 0,
            "dmnnum" => $dmnnum,
            "datatype" => "gobjectlist",
            "cmd" => $uname . ' "gobject list"',
            "file" => "/dev/shm/dmnctl/$uname.$tmpdate.gobject_list.json");
          $commands[] = array("status" => 0,
              "dmnnum" => $dmnnum,
              "datatype" => "getgovernanceinfo",
              "cmd" => $uname . ' getgovernanceinfo',
              "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getgovernanceinfo.json");
      }
      // v12.0 (vh=3)
      else {
        $commands[] = array("status" => 0,
            "dmnnum" => $dmnnum,
            "datatype" => "mnbudgetshow",
            "cmd" => $uname . ' "mnbudget show"',
            "file" => "/dev/shm/dmnctl/$uname.$tmpdate.mnbudget_show.json");
        $commands[] = array("status" => 0,
            "dmnnum" => $dmnnum,
            "datatype" => "mnbudgetfinal",
            "cmd" => $uname.' "mnfinalbudget show"',
            "file" => "/dev/shm/dmnctl/$uname.$tmpdate.mnfinalbudget_show.json");
        $commands[] = array("status" => 0,
            "dmnnum" => $dmnnum,
            "datatype" => "mnbudgetprojection",
            "cmd" => $uname.' "mnbudget projection"',
            "file" => "/dev/shm/dmnctl/$uname.$tmpdate.mnbudget_projection.json");
      }
    }
    // Only vh 2 and below
    if (($dmnpidinfo['pidstatus']) && ($dmnpidinfo['currentbin'] != '') && ($dmnpidinfo['versionhandling'] <= 2)) {
      $commands[] = array("status" => 0,
        "dmnnum" => $dmnnum,
        "datatype" => "mncurrent",
        "cmd" => $uname.' "masternode current"',
        "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_current.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnlist",
                          "cmd" => $uname.' "masternode list"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mndonation",
                          "cmd" => $uname.' "masternode list donation"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_donation.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnvotes",
                          "cmd" => $uname.' "masternode list votes"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_votes.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnlastseen",
                          "cmd" => $uname.' "masternode list lastseen"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_lastseen.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnpubkey",
                          "cmd" => $uname.' "masternode list pubkey"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_pubkey.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnpose",
                          "cmd" => $uname.' "masternode list pose"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_pose.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "mnactiveseconds",
                          "cmd" => $uname.' "masternode list activeseconds"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.masternode_list_activeseconds.json");
    }
  }

  // All vh
  foreach($dmnpid as $dmnnum => $dmnpidinfo) {
    $uname = $dmnpidinfo['uname'];
    if (($dmnpidinfo['pidstatus']) && ($dmnpidinfo['currentbin'] != '')) {
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "spork",
                          "cmd" => $uname.' "spork show"',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.spork_show.json");
    }
  }

  dmn_ctlrpc($commands);

  xecho("Parsing results...\n");
  foreach($commands as $command) {
    if ($command['status'] != 2) {
      $res = false;
      xecho("Command failed (".$command['cmd'].") [".$command['result']."]\n");
    }
    else {
      $res = file_get_contents($command['file']);
      if ($res !== false) {
        if ($command['datatype'] == 'mnpubkey') {
          $res = explode(",",substr($res,1,-1));
          $pubkeys = array();
          foreach($res as $line) {
            $raw = explode(":",$line);
            if (is_array($raw) && (count($raw) == 3)) {
              $ip = substr(trim($raw[0]),1);
              $port = substr(trim($raw[1]),0,-1);
              $pubkey = substr(trim($raw[2]),1,-1);
              $pubkeys[] = array("ip" => $ip, "port" => $port, "pubkey" => $pubkey);
            }
          }
          $res = $pubkeys;
        }
        elseif ($command['datatype'] == 'mndonation') {
          $res = explode(",",substr($res,1,-1));
          $pubkeys = array();
          foreach($res as $line) {
            $raw = explode(":",$line);
            if (is_array($raw)) {
              if (count($raw) == 4) {
                $ip = substr(trim($raw[0]),1);
                $port = substr(trim($raw[1]),0,-1);
                $pubkey = substr(trim($raw[2]),1);
                $percent = substr(trim($raw[3]),0,-1);
                $pubkeys[] = array("ip" => $ip, "port" => $port, "pubkey" => $pubkey, "percent" => intval($percent));
              }
              elseif (count($raw) == 3) {
                $ip = substr(trim($raw[0]),1);
                $port = substr(trim($raw[1]),0,-1);
                $pubkey = substr(trim($raw[2]),1);
                $pubkeys[] = array("ip" => $ip, "port" => $port, "pubkey" => '', "percent" => 0);
              }
            }
          }
          $res = $pubkeys;
        }
        elseif ($command['datatype'] != 'mncurrent') {
          $res = json_decode($res,true);
          if ($res === false) {
            xecho("Could not decode JSON from ".$command['file']."\n");
          }
          if (array_key_exists('result',$res)) {
            $res = $res['result'];
          }
        }
      }
      else {
        xecho("Could not read file: ".$command['file']."\n");
      }
      if (!unlink($command['file'])) {
        xecho("Could not delete file: ".$command['file']."\n");
      }
    }
    $dmnpid[$command['dmnnum']][$command['datatype']] = $res;
  }

  $commands = array();
  $nbuname = 5;
  $nbversion = 7;
  $nbprotocol = 8;
  $nbblocks = 6;
  $nbconnections = 4;
  $nbpid = 3;
  foreach($dmnpid as $dmnnum => $dmnpidinfo) {
    $uname = $dmnpidinfo['uname'];
    if (strlen($dmnpidinfo['pid']) > $nbpid) {
      $nbpid = strlen($dmnpidinfo['pid']);
    }
    if (strlen($uname) > $nbuname) {
      $nbuname = strlen($uname);
    }
    // If the version is 0.16+ we need to fetch "info" from "info1" and "info2"
    if ($dmnpidinfo['versionhandling'] >= 7) {
      $dmnpidinfo['info'] = array('version' => '', 'protocolversion' => 0, 'connections' => 0, 'blocks', 0);
      if (array_key_exists('info1',$dmnpidinfo)) {
        $dmnpidinfo['info']['version'] = $dmnpidinfo['info1']["version"];
        $dmnpidinfo['info']['protocolversion'] = $dmnpidinfo['info1']["protocolversion"];
        $dmnpidinfo['info']['connections'] = $dmnpidinfo['info1']["connections"];
      }
      if (array_key_exists('info1',$dmnpidinfo)) {
        $dmnpidinfo['info']['blocks'] = $dmnpidinfo['info2']["blocks"];
      }
      $dmnpid[$dmnnum]['info'] = $dmnpidinfo['info'];
    }
    if (array_key_exists('info',$dmnpidinfo)) {
      if (strlen($dmnpidinfo['info']['version']) > $nbversion) {
        $nbversion = strlen($dmnpidinfo['info']['version']);
      }
      if (strlen($dmnpidinfo['info']['protocolversion']) > $nbprotocol) {
        $nbprotocol = strlen($dmnpidinfo['info']['protocolversion']);
      }
      if (strlen($dmnpidinfo['info']['blocks']) > $nbblocks) {
        $nbblocks = strlen($dmnpidinfo['info']['blocks']);
      }
      if (strlen($dmnpidinfo['info']['connections']) > $nbconnections) {
        $nbconnections = strlen($dmnpidinfo['info']['connections']);
      }
    }
    if (($dmnpidinfo['pidstatus']) && ($dmnpidinfo['currentbin'] != '')) {
      $commands[] = array("status" => 0,
        "dmnnum" => $dmnnum,
        "datatype" => "blockhash",
        "cmd" => $uname . ' "getblockhash ' . $dmnpidinfo['info']['blocks'] . '"',
        "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getblockhash.json");
      $commands[] = array("status" => 0,
                          "dmnnum" => $dmnnum,
                          "datatype" => "networkhashps",
                          "cmd" => $uname.' getnetworkhashps',
                          "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getnetworkhashps.json");
      if (($dmnpidinfo['versionhandling'] == 3) && array_key_exists("mnbudgetshow",$dmnpidinfo) && is_array($dmnpidinfo["mnbudgetshow"])) {
        foreach ($dmnpidinfo["mnbudgetshow"] as $mnbudgetid => $mnbudgetdata) {
          $commands[] = array("status" => 0,
              "dmnnum" => $dmnnum,
              "datatype" => "mnbudget-getvotes-" . $mnbudgetid,
              "cmd" => $uname . ' "mnbudget getvotes ' . $mnbudgetid . '"',
              "file" => "/dev/shm/dmnctl/$uname.$tmpdate.mnbudget_getvotes_$mnbudgetid.json");
        }
      }
      elseif ($dmnpidinfo['versionhandling'] >= 4) {
        if  (array_key_exists("getgovernanceinfo",$dmnpidinfo) && is_array($dmnpidinfo["getgovernanceinfo"])) {
            $commands[] = array("status" => 0,
                "dmnnum" => $dmnnum,
                "datatype" => "getsuperblockbudget",
                "cmd" => $uname . ' "getsuperblockbudget '.$dmnpidinfo["getgovernanceinfo"]["nextsuperblock"].'"',
                "file" => "/dev/shm/dmnctl/$uname.$tmpdate.getsuperblockbudget.json");
        }
        if  (array_key_exists("gobjectlist",$dmnpidinfo) && is_array($dmnpidinfo["gobjectlist"])) {
          $gobjectproposals = array();
          $gobjecttriggers = array();
          foreach ($dmnpidinfo["gobjectlist"] as $gobjecthash => $gobjectdata) {
            if (is_array($gobjectdata) && array_key_exists("DataString",$gobjectdata)) {
              $gobjectdata2 = json_decode($gobjectdata["DataString"],true);
              if ($gobjectdata2 === false) {
                 xecho("Could not decode JSON from gobject ".$gobjecthash."\n");
              }
              elseif (!is_array($gobjectdata2)) {
                 xecho("Incorrect JSON from gobject ".$gobjecthash." : not an array\n");
              }
              elseif (array_key_exists("type",$gobjectdata2) && ($gobjectdata2["type"] == 2)) {
                $gobjectdata2["hash"] = $gobjecthash;
                $gobjectdata2["gobject"] = $gobjectdata;
                $gobjecttriggers[] = $gobjectdata2;
                $commands[] = array("status" => 0,
                  "dmnnum" => $dmnnum,
                  "datatype" => "gobject-getvotes-" . $gobjecthash,
                  "cmd" => $uname . ' "gobject getcurrentvotes ' . $gobjecthash . '"',
                  "file" => "/dev/shm/dmnctl/$uname.$tmpdate.gobject_getvotes_$gobjecthash.json");
              }
              elseif (array_key_exists("type",$gobjectdata2) && ($gobjectdata2["type"] == 1)) {
                $gobjectdata2["hash"] = $gobjecthash;
                $gobjectdata2["gobject"] = $gobjectdata;
                $gobjectproposals[] = $gobjectdata2;
                $commands[] = array("status" => 0,
                  "dmnnum" => $dmnnum,
                  "datatype" => "gobject-getvotes-" . $gobjecthash,
                  "cmd" => $uname . ' "gobject getcurrentvotes ' . $gobjecthash . '"',
                  "file" => "/dev/shm/dmnctl/$uname.$tmpdate.gobject_getvotes_$gobjecthash.json");
              }
              elseif ($gobjectdata2[0][0] == "proposal") {
                $gobjectdata2[0][1]["hash"] = $gobjecthash;
                $gobjectdata2[0][1]["gobject"] = $gobjectdata;
                unset($gobjectdata2[0][1]["gobject"]["DataHex"],$gobjectdata2[0][1]["gobject"]["DataString"]);
                $gobjectproposals[] = $gobjectdata2[0][1];
                $commands[] = array("status" => 0,
                                    "dmnnum" => $dmnnum,
                                    "datatype" => "gobject-getvotes-" . $gobjecthash,
                                    "cmd" => $uname . ' "gobject getcurrentvotes ' . $gobjecthash . '"',
                                    "file" => "/dev/shm/dmnctl/$uname.$tmpdate.gobject_getvotes_$gobjecthash.json");
              }
              elseif ($gobjectdata2[0][0] == "trigger") {
                  $gobjectdata2[0][1]["hash"] = $gobjecthash;
                  $gobjectdata2[0][1]["gobject"] = $gobjectdata;
                  unset($gobjectdata2[0][1]["gobject"]["DataHex"],$gobjectdata2[0][1]["gobject"]["DataString"]);
                  $gobjecttriggers[] = $gobjectdata2[0][1];
                  $commands[] = array("status" => 0,
                      "dmnnum" => $dmnnum,
                      "datatype" => "gobject-getvotes-" . $gobjecthash,
                      "cmd" => $uname . ' "gobject getcurrentvotes ' . $gobjecthash . '"',
                      "file" => "/dev/shm/dmnctl/$uname.$tmpdate.gobject_getvotes_$gobjecthash.json");
              }
              else {
                xecho("Incorrect JSON from gobject ".$gobjecthash." : not recognized (".count($gobjectdata2)." entrie(s))\n");
                var_dump($gobjectdata);
              }
            }
          }
          $dmnpid[$dmnnum]["gobjectlist"] = array("proposals" => $gobjectproposals, "triggers" => $gobjecttriggers);
        }
      }
      // If v0.13+ (vh=6+) deterministic masternode data (ProTx)
      if ($dmnpidinfo['versionhandling'] >= 6) {
          $commands[] = array("status" => 0,
              "dmnnum" => $dmnnum,
              "datatype" => "protx-valid",
              "timeout" => 30,
              "cmd" => $uname . ' "protx list valid true"',
              "file" => "/dev/shm/dmnctl/$uname.$tmpdate.protx_valid.json");
          $commands[] = array("status" => 0,
              "dmnnum" => $dmnnum,
              "datatype" => "protx-registered",
              "timeout" => 30,
              "cmd" => $uname . ' "protx list registered true"',
              "file" => "/dev/shm/dmnctl/$uname.$tmpdate.protx_registered.json");
      }
    }
  }

  dmn_ctlrpc($commands);

  xecho("Parsing results...\n");
  foreach($commands as $command) {
    if ($command['status'] != 2) {
      $res = false;
    }
    else {
      $res = file_get_contents($command['file']);
      if ($res === false) {
        xecho("Could not read file: ".$command['file']."\n");
      }
      if (!unlink($command['file'])) {
        xecho("Could not delete file: ".$command['file']."\n");
      }
      if (((strlen($command['datatype']) > 18) && (substr($command['datatype'],0,18) == 'mnbudget-getvotes-'))
       || ((strlen($command['datatype']) > 17) && (substr($command['datatype'],0,17) == 'gobject-getvotes-'))
       || ((strlen($command['datatype']) > 5) && (substr($command['datatype'],0,5) == 'protx'))) {
        $res = json_decode($res,true);
        if ($res === false) {
          xecho("Could not decode JSON from ".$command['file']."\n");
        }
        if (array_key_exists('result',$res)) {
          $res = $res['result'];
        }
      }
    }
    $dmnpid[$command['dmnnum']][$command['datatype']] = $res;
  }

  xecho(str_pad("Node",$nbuname)." ".str_pad("PID",$nbpid)." ST ".str_pad("Version",$nbversion)." ".str_pad("Protocol",$nbprotocol)." ".str_pad("Blocks",$nbblocks)." ".str_pad("Hash",64)." ".str_pad("Conn",$nbconnections)." V IP\n");
  $separator = str_repeat("-",$nbuname+$nbpid+$nbversion+$nbprotocol+$nbblocks+109)."\n";
  xecho($separator);

  $networkhashps = false;
  $networkhashpstest = false;
  $governancebudget = array(false,false);
  $governancenextsb = array(false,false);

  $spork = array();

  $mninfo2 = array();
  $mnbudgetshow = array();
  $mnbudgetprojection = array(array(),array());
  $mnbudgetfinal = array();
  $mndonationlistfinal = array();
  $mnvoteslistfinal = array();
  $mnbudgetvotes = array(array(),array());
  $gobjectproposallist = array();
  $gobjecttriggerlist = array();
  $gobjectvotes = array(array(),array());
  $dmnpidtorestart = array();

  $protxglobal = array(array(),array());

  // Go through all nodes
  foreach($dmnpid as $dmnnum => $dmnpidinfo) {

    // Get the uname
    $uname = $dmnpidinfo['uname'];
    $conf = $dmnpidinfo['conf'];

    // Is the node enabled in the configuration
    $dmnenabled = $dmnpidinfo['enabled'];

    // Get default port
    if ($dmnpidinfo['conf']->getconfig('testnet') == '1') {
      $port = 13454;
    }
    else {
      $port = 12455;
    }

    // Default values
    $iponly = '';
    $version = 0;
    $protocol = 0;
    $blocks = 0;
    $blockhash = '';
    $connections = 0;
    $country = '';
    $countrycode = '';
    $spork[$uname] = array();

    // Indicate what we are doing
    xecho(str_pad($uname,$nbuname)." ".str_pad($dmnpidinfo['pid'],$nbpid,' ',STR_PAD_LEFT)." ");

    // If the process is running
    if (($dmnpidinfo['pid'] !== false) && ($dmnpidinfo['currentbin'] != '')) {

      // Spork info
      if (array_key_exists("spork",$dmnpidinfo)) {
        $spork[$uname] = $dmnpidinfo['spork'];
      }
      else {
        $spork[$uname] = array();
      }

      // Parse status
      $gobytedinfo = dmn_getstatus($dmnpidinfo['info'],$dmnpidinfo['blockhash']);
      $blocks = $gobytedinfo['blocks'];
      $blockhash = $gobytedinfo['blockhash'];
      $connections = $gobytedinfo['connections'];
      $difficulty = $gobytedinfo['difficulty'];
      $protocol = $gobytedinfo['protocol'];
      $version = $gobytedinfo['version'];

      // Protocol
      //  Current protocol is the max protocol
      if ($curprotocol < $protocol) {
        $curprotocol = $protocol;
      }
      //  Old protocol is the min protocol
      if ($oldprotocol > $protocol) {
        $oldprotocol = $protocol;
      }
      //  Store the protocol of this node
      $protocolinfo[$uname] = $protocol;

      // Store the networkhash
      $networkhashps = intval($dmnpidinfo['networkhashps']);

      // If the version could be retrieved
      if ($version !== false) {
        // Our node is active
        $daemonactive[] = $uname;

        // Remove the notresponding counter file
        if (file_exists(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter")) {
          unlink(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter");
        }

        // Retrieve the IP from the node
        $ip = dmn_getip($dmnpidinfo['pid'],$uname);
        $dmnip = $ip;
        $country = dmn_getcountry($ip,$countrycode);
        if ($country === false) {
          $country = 'Unknown';
          $countrycode = '__';
        }

        // Default values
        $processstatus = 'running';

        // Display some feedback
        echo "\e[92mOK\e[0m ";
        echo str_pad($version,$nbversion,' ',STR_PAD_LEFT)
        ." ".str_pad($protocol,$nbprotocol,' ',STR_PAD_LEFT)
        ." ".str_pad($blocks,$nbblocks,' ',STR_PAD_LEFT)
        ." $blockhash "
        .str_pad($connections,$nbconnections,' ',STR_PAD_LEFT)." ";

        // Store the max difficulty
        if ($difficulty > $difficultyfinal) {
          $difficultyfinal = $difficulty;
        }

        // Indicates what version handling we are using
        echo $dmnpidinfo['versionhandling'];

        // Old version handling (1 & 2)
        if ($dmnpidinfo['versionhandling'] <= 2) {
          $mnpose = $dmnpidinfo['mnpose'];
          $mnlist = $dmnpidinfo['mnlist'];
          $mncurrentip = $dmnpidinfo['mncurrent'];
          $mncurrentlist[$uname] = $mncurrentip.":".$gobytedinfo['testnet'];
          foreach($dmnpidinfo['mnlastseen'] as $mnlsip => $data) {
            $mnlastseen[$uname][$mnlsip.':'.$gobytedinfo['testnet']] = $data;
          }
          foreach($dmnpidinfo['mnactiveseconds'] as $mnlsip => $data) {
            $mnactivesince[$uname][$mnlsip.':'.$gobytedinfo['testnet']] = $data;
          }
          $mndonationlist = $dmnpidinfo['mndonation'];
          $mnvoteslist = $dmnpidinfo['mnvotes'];
          $mnpubkeylist = $dmnpidinfo['mnpubkey'];
          foreach($mnlist as $ip => $activetrue) {
            if ($activetrue != 1) {
              if (($activetrue == "ENABLED") || ($activetrue == "PRE_ENABLED") || ($activetrue == "WATCHDOG_EXPIRED")) {
                $active = 1;
              }
              else {
                $active = 0;
              }
              if (!in_array($activetrue,$mnstatusexvalues,true)) {
                echo "\nWARNING: ".$ip." - Unknown StatusEx: [".$activetrue."]\n";
                $activetrue = "__UNKNOWN__";
              }
            }
            else {
              $active = $activetrue;
            }
            $mnlistfinal["$ip:".$gobytedinfo['testnet']][$uname] = array('Status' => $active,
                                                                           'PoS' => $mnpose[$ip],
                                                                           'StatusEx' => $activetrue);
          }
          if (is_array($mnvoteslist) && (count($mnvoteslist)>0)) {
            foreach($mnvoteslist as $ip => $vote) {
              $mnvoteslistfinal["$ip:".$gobytedinfo['testnet']][$uname] = $vote;
            }
          }
          foreach($mnpubkeylist as $data) {
            $mnpubkeylistfinal[$data["ip"].":".$data["port"].":".$gobytedinfo['testnet'].":".$data["pubkey"]] = array(
                     "MasternodeIP" => $data["ip"],
                     "MasternodePort" => $data["port"],
                     "MNTestNet" => $gobytedinfo['testnet'],
                     "MNPubKey" => $data["pubkey"]
                );
          }
          if (is_array($mndonationlist)) {
            foreach($mndonationlist as $donatedata) {
              $mndonationlistfinal[$donatedata["ip"].":".$donatedata["port"].":".$gobytedinfo['testnet'].":".$donatedata["pubkey"]] = array(
                     "MasternodeIP" => $donatedata["ip"],
                     "MasternodePort" => $donatedata["port"],
                     "MNTestNet" => $gobytedinfo['testnet'],
                     "MNPubKey" => $donatedata["pubkey"],
                     "MNDonationPercentage" => $donatedata["percent"]
                );
            }
          }
        }
        // New version handling (3+) [v12+]
        elseif ($dmnpidinfo['versionhandling'] >= 3) {

          // Old budget handling (3) [v12.0]
          if ($dmnpidinfo['versionhandling'] == 3) {
              // Parse masternode budgets proposals
              if (is_array($dmnpidinfo['mnbudgetshow'])) {
                  foreach ($dmnpidinfo['mnbudgetshow'] as $mnbudgetid => $mnbudgetdata) {
                      if (array_key_exists($gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"], $mnbudgetshow)) {
                          if (($mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["Yeas"]
                                  + $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["Nays"]
                                  + $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["Abstains"]) < ($mnbudgetdata["Yeas"] + $mnbudgetdata["Nays"] + $mnbudgetdata["Abstains"])
                          ) {
                              $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]] = $mnbudgetdata;
                              $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]['BudgetId'] = $mnbudgetid;
                              $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                          }
                      } else {
                          $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]] = $mnbudgetdata;
                          $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]['BudgetId'] = $mnbudgetid;
                          $mnbudgetshow[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                      }
                      if (array_key_exists("mnbudget-getvotes-" . $mnbudgetid, $dmnpidinfo)) {
                          if (!array_key_exists($mnbudgetid, $mnbudgetvotes[$gobytedinfo['testnet']])) {
                              $mnbudgetvotes[$gobytedinfo['testnet']][$mnbudgetid] = array();
                          }
                          if (is_array($dmnpidinfo["mnbudget-getvotes-" . $mnbudgetid])) {
                              foreach ($dmnpidinfo["mnbudget-getvotes-" . $mnbudgetid] as $mnbudgetvotehash => $mnbudgetvotedata) {
                                  if (array_key_exists($mnbudgetvotehash, $mnbudgetvotes[$gobytedinfo['testnet']][$mnbudgetid])) {
                                      if ($mnbudgetvotes[$gobytedinfo['testnet']][$mnbudgetid][$mnbudgetvotehash]["nTime"] < $mnbudgetvotedata["nTime"]) {
                                          $mnbudgetvotes[$gobytedinfo['testnet']][$mnbudgetid][$mnbudgetvotehash] = $mnbudgetvotedata;
                                      }
                                  } else {
                                      $mnbudgetvotes[$gobytedinfo['testnet']][$mnbudgetid][$mnbudgetvotehash] = $mnbudgetvotedata;
                                  }
                              }
                          }
                      }
                  }
              }

              // Parse masternode budgets projections
              if (is_array($dmnpidinfo['mnbudgetprojection'])) {
                  foreach ($dmnpidinfo['mnbudgetprojection'] as $mnbudgetid => $mnbudgetdata) {
                      if (is_array($mnbudgetdata) && array_key_exists("Yeas", $mnbudgetdata) && array_key_exists("Nays", $mnbudgetdata) && array_key_exists("Abstains", $mnbudgetdata)) {
                          if (array_key_exists($mnbudgetdata["Hash"], $mnbudgetprojection[$gobytedinfo['testnet']])) {
                              if (($mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]["Yeas"]
                                      + $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]["Nays"]
                                      + $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]["Abstains"]) < ($mnbudgetdata["Yeas"] + $mnbudgetdata["Nays"] + $mnbudgetdata["Abstains"])
                              ) {
                                  $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]] = $mnbudgetdata;
                                  $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]['BudgetId'] = $mnbudgetid;
                                  $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                              }
                          } else {
                              $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]] = $mnbudgetdata;
                              $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]['BudgetId'] = $mnbudgetid;
                              $mnbudgetprojection[$gobytedinfo['testnet']][$mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                          }
                      }
                  }
              }

              // Parse masternode final budget
              if (is_array($dmnpidinfo['mnbudgetfinal'])) {
                  foreach ($dmnpidinfo['mnbudgetfinal'] as $mnbudgetid => $mnbudgetdata) {
                      if (array_key_exists($gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"], $mnbudgetfinal) &&
                          array_key_exists("VoteCount", $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]])
                      ) {
                          if (($mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["VoteCount"]) < ($mnbudgetdata["VoteCount"])) {
                              $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]] = $mnbudgetdata;
                              $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]['BudgetName'] = $mnbudgetid;
                              $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                          }
                      } else {
                          $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]] = $mnbudgetdata;
                          $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]['BudgetName'] = $mnbudgetid;
                          $mnbudgetfinal[$gobytedinfo['testnet'] . "-" . $mnbudgetdata["Hash"]]["BudgetTesnet"] = $gobytedinfo['testnet'];
                      }
                  }
              }
          }
          // gobject proposals and triggers handling (4) [v12.1]
          elseif (($dmnpidinfo['versionhandling'] >= 4) && ($dmnpidinfo['type'] != 'p2pool')) {
              $collateralregexp = "/([\dabcdef]{64})-(\d+)/";
              // Store the next superblock
              if (($governancenextsb[$gobytedinfo['testnet']] === false) || ($governancenextsb[$gobytedinfo['testnet']] > intval($dmnpidinfo['getgovernanceinfo']['nextsuperblock']))) {
                $governancenextsb[$gobytedinfo['testnet']] = intval($dmnpidinfo['getgovernanceinfo']['nextsuperblock']);
              }
              // Store the budget available in next superblock
              if (($governancebudget[$gobytedinfo['testnet']] === false) || ($governancebudget[$gobytedinfo['testnet']] > floatval($dmnpidinfo['getsuperblockbudget']))) {
                $governancebudget[$gobytedinfo['testnet']] = floatval($dmnpidinfo['getsuperblockbudget']);
              }
              // Parse proposals
              if (is_array($dmnpidinfo["gobjectlist"]) && is_array($dmnpidinfo["gobjectlist"]["proposals"])) {
                  foreach ($dmnpidinfo["gobjectlist"]["proposals"] as $proposaldata) {
                      if (array_key_exists($gobytedinfo['testnet'] . "-" . $proposaldata["hash"], $gobjectproposallist)) {
                          if (($gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]]["gobject"]["YesCount"]
                             + $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]]["gobject"]["NoCount"]
                             + $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]]["gobject"]["AbstainCount"]) < ($proposaldata["gobject"]["YesCount"] + $proposaldata["gobject"]["NoCount"] + $proposaldata["gobject"]["AbstainCount"])
                          ) {
                              $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]] = $proposaldata;
                              $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]]["Testnet"] = $gobytedinfo['testnet'];
                          }
                      } else {
                          $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]] = $proposaldata;
                          $gobjectproposallist[$gobytedinfo['testnet'] . "-" . $proposaldata["hash"]]["Testnet"] = $gobytedinfo['testnet'];
                      }
                      if (array_key_exists("gobject-getvotes-" . $proposaldata["hash"], $dmnpidinfo)) {
                          if (!array_key_exists($proposaldata["hash"], $gobjectvotes[$gobytedinfo['testnet']])) {
                              $gobjectvotes[$gobytedinfo['testnet']][$proposaldata["hash"]] = array();
                          }
                          if (is_array($dmnpidinfo["gobject-getvotes-" . $proposaldata["hash"]])) {
                              foreach ($dmnpidinfo["gobject-getvotes-" . $proposaldata["hash"]] as $gobjectvotehash => $gobjectvotedata) {
                                list($collateral,$ntime,$vote,$signal) = explode(":",$gobjectvotedata);
                                if (strcasecmp($signal,"FUNDING") == 0) {
                                  $matches = array();
                                  if ((substr($collateral,0,16) == "CTxIn(COutPoint(") && (substr($collateral,-14) == "), scriptSig=)")) {
                                      $collateral = substr($collateral, 16, strlen($collateral) - 30);
                                      list($mnoutputhash, $mnoutputindex) = explode(", ", $collateral);
                                  }
                                  elseif (preg_match($collateralregexp,$collateral,$matches) == 1) {
                                      list($empty, $mnoutputhash, $mnoutputindex) = $matches;
                                  }
                                  else {
                                      $mnoutputhash = false;
                                  }
                                  if ($mnoutputhash !== false) {
                                    if (in_array($vote,GOVERNANCE_VOTES_TYPES)) {
                                      if (array_key_exists($mnoutputhash . "-" . $mnoutputindex, $gobjectvotes[$gobytedinfo['testnet']][$proposaldata["hash"]])) {
                                        if ($gobjectvotes[$gobytedinfo['testnet']][$proposaldata["hash"]][$mnoutputhash . "-" . $mnoutputindex]["nTime"] < $ntime) {
                                          $gobjectvotes[$gobytedinfo['testnet']][$proposaldata["hash"]][$mnoutputhash . "-" . $mnoutputindex] = array("MasternodeOutputHash" => $mnoutputhash,
                                            "MasternodeOutputIndex" => intval($mnoutputindex),
                                            "VoteHash" => $gobjectvotehash,
                                            "nTime" => intval($ntime),
                                            "Vote" => $vote);
                                        }
                                      } else {
                                        $gobjectvotes[$gobytedinfo['testnet']][$proposaldata["hash"]][$mnoutputhash . "-" . $mnoutputindex] = array("MasternodeOutputHash" => $mnoutputhash,
                                          "MasternodeOutputIndex" => intval($mnoutputindex),
                                          "VoteHash" => $gobjectvotehash,
                                          "nTime" => intval($ntime),
                                          "Vote" => $vote);
                                      }
                                    }
                                    else {
                                      xecho("Unknown vote type '".$vote."' on ".$dmnpidinfo["uname"]." (testnet=".$gobytedinfo['testnet'].") for proposal hash ".$proposaldata["hash"]." from masternode ".$mnoutputhash . "-" . $mnoutputindex." vote hash ".$gobjectvotehash.".\n");
                                    }
                                  }
                                }
                              }
                          }
                      }
                  }
              }
              if (is_array($dmnpidinfo["gobjectlist"]) && is_array($dmnpidinfo["gobjectlist"]["triggers"])) {
                  foreach ($dmnpidinfo["gobjectlist"]["triggers"] as $triggerdata) {
                      if (array_key_exists($gobytedinfo['testnet'] . "-" . $triggerdata["hash"], $gobjecttriggerlist)) {
                          if (($gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]]["gobject"]["YesCount"]
                                  + $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]]["gobject"]["NoCount"]
                                  + $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]]["gobject"]["AbstainCount"]) < ($triggerdata["gobject"]["YesCount"] + $triggerdata["gobject"]["NoCount"] + $triggerdata["gobject"]["AbstainCount"])
                          ) {
                              $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]] = $triggerdata;
                              $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]]["Testnet"] = $gobytedinfo['testnet'];

                          }
                      } else {
                          $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]] = $triggerdata;
                          $gobjecttriggerlist[$gobytedinfo['testnet'] . "-" . $triggerdata["hash"]]["Testnet"] = $gobytedinfo['testnet'];
                      }
                      if (array_key_exists("gobject-getvotes-" . $triggerdata["hash"], $dmnpidinfo)) {
                          if (!array_key_exists($triggerdata["hash"], $gobjectvotes[$gobytedinfo['testnet']])) {
                              $gobjectvotes[$gobytedinfo['testnet']][$triggerdata["hash"]] = array();
                          }
                          if (is_array($dmnpidinfo["gobject-getvotes-" . $triggerdata["hash"]])) {
                              foreach ($dmnpidinfo["gobject-getvotes-" . $triggerdata["hash"]] as $gobjectvotehash => $gobjectvotedata) {
                                  list($collateral,$ntime,$vote,$signal) = explode(":",$gobjectvotedata);
                                  if (strcasecmp($signal,"FUNDING") == 0) {
                                    $matches = array();
                                    if ((substr($collateral,0,16) == "CTxIn(COutPoint(") && (substr($collateral,-14) == "), scriptSig=)")) {
                                        $collateral = substr($collateral, 16, strlen($collateral) - 30);
                                        list($mnoutputhash, $mnoutputindex) = explode(", ", $collateral);
                                    }
                                    elseif (preg_match($collateralregexp,$collateral,$matches) == 1) {
                                      list($empty, $mnoutputhash, $mnoutputindex) = $matches;
                                    }
                                    else {
                                      $mnoutputhash = false;
                                    }
                                    if ($mnoutputhash !== false) {
                                      if (array_key_exists($mnoutputhash . "-" . $mnoutputindex, $gobjectvotes[$gobytedinfo['testnet']][$triggerdata["hash"]])) {
                                        if ($gobjectvotes[$gobytedinfo['testnet']][$triggerdata["hash"]][$mnoutputhash . "-" . $mnoutputindex]["nTime"] < $ntime) {
                                          $gobjectvotes[$gobytedinfo['testnet']][$triggerdata["hash"]][$mnoutputhash . "-" . $mnoutputindex] = array("MasternodeOutputHash" => $mnoutputhash,
                                            "MasternodeOutputIndex" => intval($mnoutputindex),
                                            "VoteHash" => $gobjectvotehash,
                                            "nTime" => intval($ntime),
                                            "Vote" => $vote);
                                        }
                                      } else {
                                        $gobjectvotes[$gobytedinfo['testnet']][$triggerdata["hash"]][$mnoutputhash . "-" . $mnoutputindex] = array("MasternodeOutputHash" => $mnoutputhash,
                                          "MasternodeOutputIndex" => intval($mnoutputindex),
                                          "VoteHash" => $gobjectvotehash,
                                          "nTime" => intval($ntime),
                                          "Vote" => $vote);
                                      }
                                    }
                                  }
                              }
                          }
                      }
                  }
              }

          }

          // Deterministic Masternode List (ProTx) (6) [v13+]
          if ($dmnpidinfo['versionhandling'] >= 6) {
            if (array_key_exists("protx-valid",$dmnpidinfo) && is_array($dmnpidinfo['protx-valid'])) {
              foreach ($dmnpidinfo['protx-valid'] as $protxhash => $protxdata) {
                if (!array_key_exists($protxdata["proTxHash"], $protxglobal[$gobytedinfo['testnet']])) {
                  $protxglobal[$gobytedinfo['testnet']][$protxdata["proTxHash"]] = $protxdata;
                  $protxglobal[$gobytedinfo['testnet']][$protxdata["proTxHash"]]["state"] = array();
                  unset($protxglobal[$gobytedinfo['testnet']][$protxdata["proTxHash"]]["wallet"]);
                  unset($protxglobal[$gobytedinfo['testnet']][$protxdata["proTxHash"]]["proTxHash"]);
                }
                $protxglobal[$gobytedinfo['testnet']][$protxdata["proTxHash"]]["state"][$uname] = $protxdata["state"];
              }
            }
          }

          // Parse the masternode list
          if ($dmnpidinfo['type'] == 'p2pool') {
              $mn3listfull = array();
          }
          else {
              $mn3listfull = $dmnpidinfo['mnlistfull'];
          }
          foreach($mn3listfull as $mn3output => $mn3data) {
            if ($dmnpidinfo['versionhandling'] < 5) {
                // Remove all extra spaces
                $mn3data = trim($mn3data);
                do {
                    $rcount = 0;
                    $mn3data = str_replace("  ", " ", $mn3data, $rcount);
                } while ($rcount > 0);
            }

            // Store each value separated by spaces
            $mn4lastpaidblock = 0;
            $mn5daemonversion = '';
            $mn5sentinelversion = '';
            $mn5sentinelstate = '';
            if ($dmnpidinfo['versionhandling'] == 3) {
              list($mn3status, $mn3protocol, $mn3pubkey, $mn3ipport, $mn3lastseen, $mn3activeseconds, $mn3lastpaid) = explode(" ",$mn3data);
            }
            elseif ($dmnpidinfo['versionhandling'] == 4) {
              list($mn3status, $mn3protocol, $mn3pubkey, $mn3lastseen, $mn3activeseconds, $mn3lastpaid, $mn4lastpaidblock, $mn3ipport) = explode(" ",$mn3data);
            }
            elseif ($dmnpidinfo['versionhandling'] >= 6) {
              $mn3status = $mn3data['status'];
              $mn3protocol = $gobytedinfo['protocol'];
              $mn3pubkey = $mn3data['payee'];
              $mn3lastseen = 0;
              $mn3activeseconds = 0;
              $mn3lastpaid = $mn3data['lastpaidtime'];
              $mn4lastpaidblock = $mn3data['lastpaidblock'];
              $mn3ipport = $mn3data['address'];
              $mn5daemonversion = '';
              $mn5sentinelversion = '';
              $mn5sentinelstate = '';
            }
            else {
                $mn3status = $mn3data['status'];
                $mn3protocol = $mn3data['protocol'];
                $mn3pubkey = $mn3data['payee'];
                $mn3lastseen = $mn3data['lastseen'];
                $mn3activeseconds = $mn3data['activeseconds'];
                $mn3lastpaid = $mn3data['lastpaidtime'];
                $mn4lastpaidblock = $mn3data['lastpaidblock'];
                $mn3ipport = $mn3data['address'];
                $mn5daemonversion = $mn3data['daemonversion'];
                $mn5sentinelversion = $mn3data['sentinelversion'];
                $mn5sentinelstate = $mn3data['sentinelstate'];
            }

            // Handle the IPs
            if (substr($mn3ipport,0,1) == "[") {
              // IPv6
              list($mn3ip, $mn3port) = explode("]:", substr($mn3ipport,1,strlen($mn3ipport)-1));
            }
            else {
              // IPv4
              $test = explode(":", $mn3ipport);
              if (!array_key_exists(1,$test)) {
                var_dump($mn3ipport);
              }
              list($mn3ip, $mn3port) = $test;
            }

            if (array_key_exists($mn3output."-".$gobytedinfo['testnet'],$mninfo2)) {
              if ($mn3lastseen < $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeLastSeen"]) {
                $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeLastSeen"] = intval($mn3lastseen);
              }
              if ($mn3activeseconds < $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeActiveSeconds"]) {
                $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeActiveSeconds"] = intval($mn3activeseconds);
              }
              if ($mn3lastpaid > $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeLastPaid"]) {
                $mninfo2[$mn3output."-".$gobytedinfo['testnet']]["MasternodeLastPaid"] = intval($mn3lastpaid);
              }
            }
            else {
              $mninfo2[$mn3output."-".$gobytedinfo['testnet']] = array("MasternodeProtocol" => intval($mn3protocol),
                                                                         "MasternodePubkey" => $mn3pubkey,
                                                                         "MasternodeIP" => $mn3ip,
                                                                         "MasternodePort" => $mn3port,
                                                                         "MasternodeLastSeen" => intval($mn3lastseen),
                                                                         "MasternodeActiveSeconds" => intval($mn3activeseconds),
                                                                         "MasternodeLastPaid" => $mn3lastpaid,
                                                                         "MasternodeLastPaidBlock" => intval($mn4lastpaidblock),
                                                                         "MasternodeDaemonVersion" => $mn5daemonversion,
                                                                         "MasternodeSentinelVersion" => $mn5sentinelversion,
                                                                         "MasternodeSentinelState" => $mn5sentinelstate);
            }
            if (($mn3status == "ENABLED") || ($mn3status == "PRE_ENABLED")) {
              $active = 1;
            }
            else {
              $active = 0;
            }
            if (!in_array($mn3status,$mnstatusexvalues,true)) {
              echo "\nWARNING: ".$mn3output." - Unknown StatusEx: [".$mn3status."] ";
              $mn3status = "__UNKNOWN__";
            }
            $mnlist2final[$mn3output."-".$gobytedinfo['testnet']][$uname] = array('Status' => $active,
                                                                                    'StatusEx' => $mn3status);
          }
        }
        echo " $dmnip\n";
      }
      elseif ($dmnenabled) {
        $iponly = $dmnpidinfo['conf']->getconfig('bind');
        $ip = "$iponly:$port";
        $country = dmn_getcountry($ip,$countrycode);
        if ($country === false) {
          $country = 'Unknown';
          $countrycode = '__';
        }
        $processstatus = 'notresponding';
        $dmnpidtorestart[$dmnnum] = $dmnpidinfo;
        echo "NR ".str_repeat(" ",96)."$ip\n";
      }
      else {
        $processstatus = 'disabled';
        echo "--\n";
      }
    }
    elseif ($dmnenabled) {
      // Remove the notresponding counter file
      if (file_exists(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter")) {
        unlink(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter");
      }
      $iponly = $dmnpidinfo['conf']->getconfig('bind');
      $ip = "$iponly:$port";
      $country = dmn_getcountry($ip,$countrycode);
      if ($country === false) {
        $country = 'Unknown';
        $countrycode = '__';
      }
      $processstatus = 'stopped';
      echo "\e[91mNS\e[0m ".str_repeat(" ",97)."$ip\n";
    }
    else {
      // Remove the notresponding counter file
      if (file_exists(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter")) {
        unlink(DMN_NRCOUNTDIR."dmnctl-NR-$uname-counter");
      }
      $processstatus = 'disabled';
      echo "\e[90m--\e[0m\n";
    }
    $wsstatus[$uname] = array("ProcessStatus" => $processstatus,
                              "Version" => $version,
                              "Protocol" => $protocol,
                              "Blocks" => $blocks,
                              "LastBlockHash" => $blockhash,
                              "Connections" => $connections,
                              "Country" => $country,
                              "CountryCode" => $countrycode,
                              "Spork" => $spork[$uname]);
  }
  xecho($separator);
  ksort($mnpubkeylistfinal,SORT_NATURAL);
  $mnlastseenfinal = array();
  foreach($mnlastseen as $uname => $mnlastseenlist) {
    foreach($mnlastseenlist as $ip => $lastseentimestamp) {
      if ((array_key_exists($ip,$mnlastseenfinal) && ($mnlastseenfinal[$ip] > $lastseentimestamp)) || !array_key_exists($ip,$mnlastseenfinal)) {
        $mnlastseenfinal[$ip] = $lastseentimestamp;
      }
    }
  }
  ksort($mnlastseenfinal,SORT_NATURAL);
  $mnactivesincefinal = array();
  foreach($mnactivesince as $uname => $mnactivesincelist) {
    foreach($mnactivesincelist as $ip => $activeseconds) {
      if ((array_key_exists($ip,$mnactivesincefinal) && ($mnactivesincefinal[$ip] < $activeseconds)) || !array_key_exists($ip,$mnactivesincefinal)) {
        $mnactivesincefinal[$ip] = $activeseconds;
      }
    }
  }
  ksort($mnactivesincefinal,SORT_NATURAL);
  $mncountinactive = 0;
  $mncountactive = 0;
  foreach($mnlistfinal as $ip => $info) {
    $inactiveresult = true;
    foreach($info as $uname => $mnactive) {
      $inactiveresult = $inactiveresult && (($mnactive == 0) || ($mnactive === false));
    }
    if ($inactiveresult ) {
      $mncountinactive++;
    }
    else {
      $mncountactive++;
    }
  }
  $mninfodel = array();
  foreach($mninfolast as $ip) {
    if (!array_key_exists($ip,$mnlistfinal)) {
      $info = explode(":",$ip);
      $mninfodel[] = array('ip' => $info[0], 'port' => $info[1]);
    }
  }
  $mncount = $mncountinactive+$mncountactive;
  if (count($mnlistfinal) > 0) {
    ksort($mnlistfinal,SORT_NATURAL);
    $estpayoutdaily = round(dmn_getpayout($mncountactive,$dashdinfo['difficulty']),2);
  }
  else {
    $estpayoutdaily = '???';
  }

  //  echo "Total Masternodes: $mncount/$mncountinactive    Est.Payout: $estpayoutdaily DASH/day (diff=$difficultyfinal)\n";

  if (count($wsstatus)>0) {
    $wsmninfo = array();
    $wsmnlist = array();
    foreach($mnlistfinal as $ip => $mninfo) {
      $ipport = explode(":",$ip);
      $mnip = $ipport[0];
      $mnport = $ipport[1];
      $mntestnet = $ipport[2];
      if (array_key_exists($ip,$mnactivesincefinal)) {
        $mnactiveseconds = $mnactivesincefinal[$ip];
      }
      else {
        $mnactiveseconds = 0;
      }
      if (array_key_exists($ip,$mnlastseenfinal)) {
        $mnlastseen = $mnlastseenfinal[$ip];
      }
      else {
        $mnlastseen = 0;
      }
      $mncountry = dmn_getcountry($ip,$mncountrycode);
      if ($mncountry === false) {
        $mncountry = 'Unknown';
        $mncountrycode = '__';
      }
      $wsmninfo[] = array("MasternodeIP" => $mnip,
                          "MasternodePort" => $mnport,
                          "MNTestNet" => $mntestnet,
                          "MNActiveSeconds" => $mnactiveseconds,
                          "MNLastSeen" => $mnlastseen,
                          "MNCountry" => $mncountry,
                          "MNCountryCode" => $mncountrycode);

      foreach($mninfo as $mnuname => $mnactive) {
        if ($mnactive['Status'] == 1) {
          if (array_key_exists($mnuname,$mncurrentlist) && ($ip == $mncurrentlist[$uname])) {
            $mnstatus = 'current';
          }
          else {
            $mnstatus = 'active';
          }
        }
        elseif ($mnactive['Status'] === false) {
          $mnstatus = 'unlisted';
        }
        else {
          $mnstatus = 'inactive';
        }
        $wsmnlist[] = array("MasternodeIP" => $mnip,
                            "MasternodePort" => $mnport,
                            "MNTestNet" => $mntestnet,
                            "FromNodeUName" => $mnuname,
                            "MasternodeStatus" => $mnstatus,
                            "MasternodeStatusPoS" => $mnactive['PoS'],
                            "MasternodeStatusEx" => $mnactive['StatusEx']);
      }
    }
    $wsmnpubkeys = array();
    foreach ($mnpubkeylistfinal as $key => $data) {
      $wsmnpubkeys[] = $data;
    }
    $wsmndonation = array();
    foreach ($mndonationlistfinal as $key => $data) {
      $wsmndonation[] = $data;
    }
    $wsmnvotes = array();
    foreach($mnvoteslistfinal as $ip => $mnvotesinfo) {
      $ipport = explode(":",$ip);
      $mnip = $ipport[0];
      $mnport = $ipport[1];
      $mntestnet = $ipport[2];
      foreach($mnvotesinfo as $mnuname => $mnvote) {
        $wsmnvotes[] = array("MasternodeIP" => $mnip,
                             "MasternodePort" => $mnport,
                             "MNTestNet" => $mntestnet,
                             "FromNodeUName" => $mnuname,
                             "MasternodeVote" => $mnvote);
      }
    }

    // v12 handling / VersionHandling = 3
    $wsmninfo2 = array();
    foreach($mninfo2 as $output => $mninfo) {
      list($mnoutputhash, $mnoutputindex, $mntestnet) = explode("-", $output);
      $wsmninfo2[] = array("MasternodeOutputHash" => $mnoutputhash,
                           "MasternodeOutputIndex" => $mnoutputindex,
                           "MasternodeTestNet" => $mntestnet,
                           "MasternodeProtocol" => $mninfo["MasternodeProtocol"],
                           "MasternodePubkey" => $mninfo["MasternodePubkey"],
                           "MasternodeIP" => $mninfo["MasternodeIP"],
                           "MasternodePort" => $mninfo["MasternodePort"],
                           "MasternodeLastSeen" => $mninfo["MasternodeLastSeen"],
                           "MasternodeActiveSeconds" => $mninfo["MasternodeActiveSeconds"],
                           "MasternodeLastPaid" => $mninfo["MasternodeLastPaid"],
                           "MasternodeLastPaidBlock" => $mninfo["MasternodeLastPaidBlock"],
                           "MasternodeDaemonVersion" => $mninfo["MasternodeDaemonVersion"],
                           "MasternodeSentinelVersion" => $mninfo["MasternodeSentinelVersion"],
                           "MasternodeSentinelState" => $mninfo["MasternodeSentinelState"]);
    }

    $wsmnlist2 = array();
    foreach($mnlist2final as $output => $mninfo) {
      list($mnoutputhash, $mnoutputindex, $mntestnet) = explode("-", $output);
      foreach($mninfo as $mnuname => $mnactive) {
        if ($mnactive['Status'] == 1) {
          $mnstatus = 'active';
        }
        elseif ($mnactive['Status'] === false) {
          $mnstatus = 'unlisted';
        }
        else {
          $mnstatus = 'inactive';
        }
        $wsmnlist2[] = array("MasternodeOutputHash" => $mnoutputhash,
                             "MasternodeOutputIndex" => $mnoutputindex,
                             "MasternodeTestNet" => $mntestnet,
                             "FromNodeUName" => $mnuname,
                             "MasternodeStatus" => $mnstatus,
                             "MasternodeStatusEx" => $mnactive['StatusEx']);
      }
    }

    $wsmnbudgetshow = array();
    foreach($mnbudgetshow as $budgetinfo) {
      $wsmnbudgetshow[] = $budgetinfo;
    }

    $wsmnbudgetvotes = array();
    foreach($mnbudgetvotes as $testnet => $mnbudgetvotesdata) {
      foreach($mnbudgetvotesdata as $budgetid => $mnbudgetvotesdata2) {
        foreach($mnbudgetvotesdata2 as $mnvotehash => $mnbudgetvotesdata3) {
          list($mnoutputhash, $mnoutputindex) = explode("-", $mnvotehash);
          $wsmnbudgetvotes[] = array(
              'BudgetTestnet' => intval($testnet),
              'BudgetId' => $budgetid,
              'MasternodeOutputHash' => $mnoutputhash,
              'MasternodeOutputIndex' => intval($mnoutputindex),
              'VoteHash' => $mnbudgetvotesdata3["nHash"],
              'VoteValue' => $mnbudgetvotesdata3["Vote"],
              'VoteTime' => $mnbudgetvotesdata3["nTime"],
              'VoteIsValid' => $mnbudgetvotesdata3["fValid"],
          );
        }
      }
    }

    $wsmnbudgetprojection = array();
    foreach($mnbudgetprojection as $mnbudgetdata) {
      foreach($mnbudgetdata as $budgetinfo) {
        $wsmnbudgetprojection[] = $budgetinfo;
      }
    }

    $wsmnbudgetfinal = array();
    foreach($mnbudgetfinal as $budgetinfo) {
        $wsmnbudgetfinal[] = $budgetinfo;
    }

    $wsgoproposals = array();
    foreach($gobjectproposallist as $proposalinfo) {
      unset($proposalinfo["gobject"]["Hash"]);
      $wsgoproposals[] = $proposalinfo;
    }

    $wsgotriggers = array();
    foreach($gobjecttriggerlist as $triggerinfo) {
      $wsgotriggers[] = $triggerinfo;
    }

    $wsgobjectvotes = array();
    foreach($gobjectvotes as $testnet => $gobjectvotesdata) {
       foreach($gobjectvotesdata as $gobjecthash => $gobjectvotedata2) {
              foreach($gobjectvotedata2 as $gobjectvotedata3) {
                  $wsgobjectvotes[] = array(
                      'GovernanceObjectTestnet' => intval($testnet),
                      'GovernanceObjectId' => $gobjecthash,
                      'MasternodeOutputHash' => $gobjectvotedata3["MasternodeOutputHash"],
                      'MasternodeOutputIndex' => $gobjectvotedata3["MasternodeOutputIndex"],
                      'VoteHash' => $gobjectvotedata3["VoteHash"],
                      'VoteValue' => $gobjectvotedata3["Vote"],
                      'VoteTime' => $gobjectvotedata3["nTime"],
                  );
              }
       }
    }

    xecho("Submitting status via webservice (".count($wsstatus)." entries): ");
    $response = '';
    $payload = array('nodes' => $wsstatus,
                     'testnet' => $istestnet,
                     'mninfo' => $wsmninfo,
                     'mninfo2' => $wsmninfo2,
                     'mnpubkeys' => $wsmnpubkeys,
                     'mndonation' => $wsmndonation,
                     'mnlist' => $wsmnlist,
                     'mnlist2' => $wsmnlist2,
                     'mnvotes' => $wsmnvotes,
                     // v0.12.0 budgets
                     'mnbudgetshow' => $wsmnbudgetshow,
                     'mnbudgetfinal' => $wsmnbudgetfinal,
                     'mnbudgetvotes' => $wsmnbudgetvotes,
                     'mnbudgetprojection' => $wsmnbudgetprojection,
                     // v0.12.1 budgets
                     'gobjproposals' => $wsgoproposals,
                     'gobjtriggers' => $wsgotriggers,
                     'gobjvotes' => $wsgobjectvotes,
                     // v0.13 protx
                     'protx' => $protxglobal,
                     'stats' => array('networkhashps' => $networkhashps,
                                      'governancenextsuperblock' => $governancenextsb[$istestnet],
                                      'governancebudget' =>  $governancebudget[$istestnet]));
    $contentraw = dmn_cmd_post('ping',$payload,$response);
    if (strlen($contentraw) > 0) {
      $content = json_decode($contentraw,true);
      if (($response['http_code'] >= 200) && ($response['http_code'] <= 299)) {
        echo "Success (".$response['http_code'].")\n";
        if (is_array($content["data"])) {
          xecho("+ Nodes: ");
          if ($content["data"]["nodes"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["nodes"]."\n";
          }
          xecho("+ Masternodes Info (<=v0.11): ");
          if ($content["data"]["mninfo"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mninfo"]."\n";
          }
          xecho("+ Masternodes Info (>=v0.12): ");
          if ($content["data"]["mninfo2"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mninfo2"]."\n";
          }
          xecho("+ Masternodes Pubkeys (<=v0.11): ");
          if ($content["data"]["mnpubkeys"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnpubkeys"]."\n";
          }
          xecho("+ Masternodes Donations (<=v0.11): ");
          if ($content["data"]["mndonation"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mndonation"]."\n";
          }
          xecho("+ Masternodes List (<=v0.11): ");
          if ($content["data"]["mnlist"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnlist"]."\n";
          }
          xecho("+ Masternodes List (=v0.12): ");
          if ($content["data"]["mnlist2"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnlist2"]."\n";
          }
          xecho("+ Deterministic Masternodes List (>=v0.13): ");
          if ($content["data"]["protx"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["protx"]."\n";
          }
          xecho("+ Deterministic Masternodes State (>=v0.13): ");
          if ($content["data"]["protxstate"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["protxstate"]."\n";
          }
          xecho("+ Masternodes Portcheck: ");
          if ($content["data"]["portcheck"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["portcheck"]."\n";
          }
          xecho("+ Masternodes Votes: ");
          if ($content["data"]["mnvotes"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnvotes"]."\n";
          }
          xecho("+ Spork: ");
          if ($content["data"]["spork"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["spork"]."\n";
          }
          xecho("+ Stats (Mainnet): ");
          if ($content["data"]["stats"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["stats"]."\n";
          }
          xecho("+ Stats (Testnet): ");
          if ($content["data"]["stats2"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["stats2"]."\n";
          }
          xecho("+ Budget (Show): ");
          if ($content["data"]["mnbudgetshow"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnbudgetshow"]."\n";
          }
          xecho("+ Budget (Projection): ");
          if ($content["data"]["mnbudgetprojection"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnbudgetprojection"]."\n";
          }
          xecho("+ Budget (Votes): ");
          if ($content["data"]["mnbudgetvotes"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnbudgetvotes"]."\n";
          }
          xecho("+ Final Budget: ");
          if ($content["data"]["mnbudgetfinal"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["mnbudgetfinal"]."\n";
          }
          xecho("+ Governance Object Proposals: ");
          if ($content["data"]["gobjproposals"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["gobjproposals"]."\n";
          }
          xecho("+ Governance Object Triggers: ");
          if ($content["data"]["gobjtriggers"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["gobjtriggers"]."\n";
          }
          xecho("+ Governance Object Triggers Payments: ");
          if ($content["data"]["gobjtriggerspayments"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["gobjtriggerspayments"]."\n";
          }
          xecho("+ Governance Object Triggers Payments Trim: ");
          if ($content["data"]["gobjtriggerspaymentstrim"] === false) {
            echo "Failed!\n";
          } else {
            echo $content["data"]["gobjtriggerspaymentstrim"]."\n";
          }
          xecho("+ Governance Object Votes: ");
          if ($content["data"]["gobjvotes"] === false) {
            echo "Failed (".$content["data"]["gobjvotes"].")!\n";
          } else {
            echo $content["data"]["gobjvotes"]."\n";
          }
        }
      }
      elseif (($response['http_code'] >= 400) && ($response['http_code'] <= 499)) {
        echo "Error (".$response['http_code'].": ".$content['message'].")\n";
      }
      elseif (($response['http_code'] >= 500) && ($response['http_code'] <= 599)) {
          echo "Unknown Error (".$response['http_code'].")\n";
          var_dump($response['http_code']);
          var_dump($content);
          var_dump($contentraw);
      }
      else {
        echo "Unknown (".$response['http_code'].")\n";
      }
    }
    else {
      echo "Error (empty result) [HTTP CODE ".$response['http_code']."]\n";
    }
  }

  if (count($dmnpidtorestart)>0) {
    dmn_restartfrozen($dmnpidtorestart);
  }

}

//#############################################################################
//#############################################################################
//
//                               MAIN PROGRAM
//
//#############################################################################
//#############################################################################

$lastrefresh = gmdate('Y-m-d H:i:s');
$starttime = microtime(true);

xecho("GoByte Ninja Control [dmnctl] v".DMN_VERSION." (".date('Y-m-d H:i:s',filemtime(__FILE__)).")\n");

// If there is at least a parameter identify the action
if ($argc > 1) {

  // Populate the $action array
  $action = array(
    "disable" => (strcasecmp($argv[1], 'disable') == 0),
    "enable" => (strcasecmp($argv[1], 'enable') == 0),
    "restart" => (strcasecmp($argv[1], 'restart') == 0),
    "start" => (strcasecmp($argv[1], 'start') == 0),
    "status" => (strcasecmp($argv[1], 'status') == 0),
    "stop" => (strcasecmp($argv[1], 'stop') == 0),
    "version" => (strcasecmp($argv[1], 'stop') == 0),
    "nodelist" => false,
    "keeprunning" => false,
  );

  // We need the node list from CMD API
  $action["nodelist"] = ( $action["status"] || $action["start"] || $action["stop"] || $action["restart"] );

  // We need to execute keeprunning portion of the script
  $action["keeprunning"] = $action["nodelist"];

  // Is is testnet?
  $istestnet = 0;
  if ($argc > 2) {
      if ( (( $action["status"] || $action["start"] || $action["stop"] || $action["restart"] )) && ((strcasecmp($argv[2], 'testnet') == 0))) {
          $istestnet = 1;
      }
  }

  // If we need the node list
  if ( $action["nodelist"] ) {

    // Retrieve node info from CMD API
    xecho("Querying list of nodes for this hub: ");
    $params = array();
    $content = dmn_cmd_get('nodes', $params, $response);
    $nodes = array();
    if (strlen($content) > 0) {
      $content = json_decode($content, true);
      if (($response['http_code'] >= 200) && ($response['http_code'] <= 299)) {
        $nodes = $content['data'];
        echo "Success (" . count($nodes) . " nodes)\n";
      } elseif (($response['http_code'] >= 400) && ($response['http_code'] <= 499)) {
        echo "Error (" . $response['http_code'] . ": " . implode(' / ', $content['messages']) . ")\n";
      } else {
        echo "Error (" . $response['http_code'] . ": " . implode(' / ', $content['messages']) . ")\n";
      }
    } else {
      echo "Error (empty result) [HTTP CODE " . $response['http_code'] . "]\n";
    }
    unset($content, $response, $params);

    // Retrieve the nodes process ids
    $dmnpid = dmn_getpids($nodes, (strcasecmp($argv[1], 'status') == 0), $istestnet);

    // Check/Start of the nodes are still running (restart them if needed)
    // (keeprunning in node configuration)
    if ($action["keeprunning"]) {
      dmn_startkeeprunning($dmnpid);
    }
  }
}

// If there are no parameters, display help
if ($argc == 1) {
  dmn_help($argv[0]);
}
// Disable nodes
// TODO update CMD API to actually disable monitoring nodes (not done in conf file anymore)
elseif ($action["disable"]) {
  $dmntodisable = array();
  for ($x = 2; $x < $argc; $x++) {
    $dmntodisable[] = $argv[$x];
  }
  dmn_disable($dmnpid,$dmntodisable);
}
// Enable nodes
// TODO update CMD API to actually enable monitoring nodes (not done in conf file anymore)
elseif ($action["enable"]) {
  $dmntoenable = array();
  for ($x = 2; $x < $argc; $x++) {
    $dmntoenable[] = $argv[$x];
  }
  dmn_enable($dmnpid,$dmntoenable);
}
// Retrieve status of monitoring nodes and submit it to CMD API
elseif ($action["status"]) {
  $semfnam = sprintf(DMN_CTLSTATUSAUTO_SEMAPHORE,$istestnet);
  file_put_contents($semfnam,sprintf('%s',getmypid()));
  dmn_status($dmnpid,$istestnet);
  unlink($semfnam);
}
// Start/Stop/Restart actions
elseif ($action["start"] || $action["stop"] || $action["restart"]) {
  $todo = strtolower($argv[1]);
  $testnet = ($argc > 2) && ($argv[2] == 'testnet');
  if (($argc > 3)
   && ((strcasecmp($argv[3],'p2pool') == 0)
    || (strcasecmp($argv[3],'masternode') == 0))) {
    $nodetype = $argv[3];
  }
  else {
    $nodetype = "masternode";
  }
  dmn_startstop($dmnpid,$todo,$testnet,$nodetype,($argc > 4) && (strcasecmp($argv[4],'reindex') == 0));
}
// Create new gobyted version in CMD API
elseif (strcasecmp($argv[1],'version') == 0) {
  if ($argc == 6) {
    dmn_version_create($argv[2],$argv[3],$argv[4],$argv[5]);
  }
  else {
    dmn_help($argv[0]);
    echo "Not enough parameters for version action.\n";
  }
}
// Create new monitoring node
elseif (strcasecmp($argv[1],'create') == 0) {
  if ($argc == 3) {
    dmn_create($dmnpid,$argv[2]);
  }
  else if ($argc == 4) {
    dmn_create($dmnpid,$argv[2],$argv[3]);
  }
  else {
    dmn_help($argv[0]);
  }
}
// If we could not find anything to do, display help
else {
  dmn_help($argv[0]);
  echo "Unknown action: ".$argv[1]."\n";
}

?>
