<?php
/**
 * Copyright (C) 2011 by iRail vzw/asbl
 *
 * @author Jens Segers
 * @author  Pieter Colpaert <pieter aŧ iRail.be>
 * @license AGPLv3
 *
 */

include_once (dirname(__FILE__) . "/Stations.class.php");
include_once (dirname(__FILE__) . "/../iRailLiveboard.class.php");

class Liveboard extends IRailLiveboard {
    
    private $system;
    
    public function setParameter($key, $val) {
        if ($key == "system" && in_array($val, iRailTools::ALLOWED_SYSTEMS)) {
            $this->system = $val;
        }
        else {
            parent::setParameter($key, $val);
        }
    }
    
    public function call() {
        ob_start();
        
        $o = new stdClass();
        
        $station = Stations::getStationFromName($this->location, $this->lang);
        $stationId = $station->id;
        
        // remove unwatend properties
        unset($station->type);
        unset($station->id);
        
        $o->location = $station;
        $o->{$this->direction} = $this->getLiveboard($stationId, $this->startdatetime, $this->enddatetime, $this->lang, $this->direction);
        
        return $o;
    }
    
    public static function getLiveboard($stationId, $startdatetime, $enddatetime, $lang, $direction) {
        
        if ($direction == "departures")
            $type = "dep";
        else
            $type = "arr";
        
        $url = "http://hari.b-rail.be/Hafas/bin/stboard.exe/" . $lang . "?start=yes";
        $url .= "&time=" . urlencode($startdatetime->format("H:i")) . "&date=o" . $startdatetime->format("d.m.Y") . "&inputTripelId=" . "A=1@O=@X=@Y=@U=80@L=" . $stationId . "@B=1@p=@" . "&maxJourneys=50&boardType=" . $type . "&hcount=1&htype=NokiaC7-00%2f022.014%2fsw_platform%3dS60%3bsw_platform_version%3d5.2%3bjava_build_version%3d2.2.54&L=vs_java3&productsFilter=0111111000000000";
        
        $request = TDT::HttpRequest($url);
        if (isset($request->error)) {
            throw new HttpOutTDTException($url);
        }
        
        $data = new SimpleXMLElement("<xml>" . $request->data . "</xml>");
        
        if(!count($data->Journey))
            throw new Exception("Unable to get results");
        
        $liveboard = array();
        
        $i = 0;
        // check first departure
        $departure = DateTime::createFromFormat('d/m/y H:i', $data->Journey[$i]["fpDate"]." ".$data->Journey[$i]["fpTime"]);
        
        while (isset($data->Journey[$i]) && $departure <= $enddatetime) {
            $journey = $data->Journey[$i];
            
            $delay = (string) $journey["delay"];
            if($delay == "-")
                $delay = 0;
            
            $platform = "";
            if (isset($journey["platform"])) {
                $platform = (string) $journey["platform"];
            }
            
            // delay to seconds
            preg_match("/\+\s*(\d+)/", $delay, $d);
            if (isset($d[1])) {
                $delay = $d[1] * 60;
            }
            preg_match("/\+\s*(\d):(\d+)/", $delay, $d);
            if (isset($d[1])) {
                $delay = $d[1] * 3600 + $d[2] * 60;
            }
            
            $station = (string) $journey["dir"];
            
            //GET VEHICLE AND PLATFORM
            $platformNormal = true;
            $veh = $journey["prod"];
            $veh = substr($veh, 0, 8);
            $veh = str_replace(" ", "", $veh);
            $vehicle = "BE.NMBS." . $veh;
            
            // TODO detect platform changed
            
            $station = Stations::getStationFromName($station, $lang);
            $direction = $station->name;
            
            $time = $departure->getTimestamp();
            
            $node = new stdClass();
            $node->time = $time;
            $node->delay = $delay;
            $node->direction = $direction;
            $node->vehicle = $vehicle;
            $node->platform = new stdClass();
            $node->platform->name = $platform;
            
            $liveboard[] = $node;
            
            $i++;
            
            // detect next departure if there is one
            if(isset($data->Journey[$i]))
                $departure = DateTime::createFromFormat('d/m/y H:i', $data->Journey[$i]["fpDate"]." ".$data->Journey[$i]["fpTime"]);
        }
        
        if($departure <= $enddatetime) {
            $next_results = self::getLiveboard($stationId, $departure, $enddatetime, $lang, $direction);
            array_push($liveboard, $next_results);
        }
        
        return $liveboard;
    }
    
    /**
     * It will remove the duplicates from an array the php way. Since a PHP array will need to recopy everything to be reindexed, I figured this would go faster if we did the deleting when copying.
     */
    private static function removeDuplicates($nodes) {
        $newarray = array();
        for($i = 0; $i < sizeof($nodes); $i++) {
            $duplicate = false;
            for($j = 0; $j < $i; $j++) {
                if ($nodes[$i]->vehicle == $nodes[$j]->vehicle) {
                    $duplicate = true;
                    continue;
                }
            }
            if (! $duplicate) {
                $newarray[sizeof($newarray)] = $nodes[$i];
            }
        }
        return $newarray;
    }
    
}

?>
