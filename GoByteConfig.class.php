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

// Deal with gobyte.conf configuration (read/write)
class GoByteConfig {

  // Normal gobyte.conf configuration
  private $config;

  // Masternode Control configuration
  private $mnctlcfg;

  // Masternode Control Magic Keyword
  const MAGIC = '#mnctlcfg#';

  // Path to config file
  private $configfilename;
  private $configloaded = false;

  // Load the config file
  private function loadconfig() {
    $this->config = array();
    $this->mnctlcfg = array();
    $this->configloaded = false;
    if (file_exists($this->configfilename)) {
      $rawconf = file_get_contents($this->configfilename);
      $conf = explode("\n",trim($rawconf));
      $magiclen = strlen(GoByteConfig::MAGIC);
      for ($x = 0; $x < count($conf); $x++) {
        if ((substr($conf[$x],0,1) == '#') && (substr($conf[$x],0,$magiclen) != GoByteConfig::MAGIC)) {
          $lineval = array(0 => $conf[$x]);
        }
        else {
          $lineval = explode('=',$conf[$x]);
        }
        if (substr($lineval[0],0,$magiclen) == GoByteConfig::MAGIC) {
          $this->mnctlcfg[substr($lineval[0],$magiclen)] = $lineval[1];
        }
        else {
          if (isset($lineval[1])) {
            $this->config[$lineval[0]] = $lineval[1];
          }
          else {
            $this->config[$lineval[0]] = false;
          }
        }
      }
      $this->configloaded = true;
    }
  }

  function __construct($uname) {

    if (file_exists('/home/'.$uname.'/.gobytecore/gobyte.conf')) {
      $this->configfilename = '/home/'.$uname.'/.gobytecore/gobyte.conf';
    }
    elseif (file_exists('/home/'.$uname.'/.gobyte/gobyte.conf')) {
      $this->configfilename = '/home/'.$uname.'/.gobyte/gobyte.conf';
    }
    else {
      $this->configfilename = '/home/'.$uname.'/.darkcoin/darkcoin.conf';
    }
    $this->loadconfig();

  }

  function getconfig($key) {

    $res = false;
    if (array_key_exists($key,$this->config)) {
      $res = $this->config[$key];
    }
    return $res;

  }

  function setconfig($key,$value) {

    $this->config[$key] = $value;

  }

  function getmnctlconfig($key) {

    $res = false;
    if (array_key_exists($key,$this->mnctlcfg)) {
      $res = $this->mnctlcfg[$key];
    }
    return $res;

  }

  function setmnctlconfig($key,$value) {

    $this->mnctlcfg[$key] = $value;

  }

  // Save the config file
  function saveconfig() {
    if ($this->configloaded) {
      $rawconf = '';
      foreach ($this->config as $key => $value) {
        if ($value === false) {
          $rawconf .= $key."\n";
        }
        else {
          $rawconf .= $key.'='.$value."\n";
        }
      }
      foreach ($this->mnctlcfg as $key => $value) {
        if ($value === false) {
          $rawconf .= GoByteConfig::MAGIC.$key."\n";
        }
        else {
          $rawconf .= GoByteConfig::MAGIC.$key.'='.$value."\n";
        }
      }
      $res = file_put_contents($this->configfilename,$rawconf);
    }
    else {
      $res = false;
    }
    return $res;
  }

  function isConfigLoaded() {
    return $this->configloaded;
  }

}

?>
