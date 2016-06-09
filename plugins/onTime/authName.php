<?php
/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2016 Robert Sardinia
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

// Auth Name Check
$loop->addPeriodicTimer(1800, function() use ($logger, $discord, $config) {
    if ($config["plugins"]["auth"]["nameEnforce"] == "true") {
        $logger->info("Initiating Name Check");
        $db = $config["database"]["host"];
        $dbUser = $config["database"]["user"];
        $dbPass = $config["database"]["pass"];
        $dbName = $config["database"]["database"];
        $guildID = $config["plugins"]["auth"]["guildID"];
        $toDiscordChannel = $config["plugins"]["auth"]["alertChannel"];
        
        $conn = new mysqli($db, $dbUser, $dbPass, $dbName);

        $sql = "SELECT characterID, discordID, eveName FROM authUsers WHERE active='yes'";

        $result = $conn->query($sql);
        $num_rows = $result->num_rows;

        if ($num_rows >= 1) {
            while ($rows = $result->fetch_assoc()) {
                $discordid = $rows['discordID'];
                $eveName = $rows['eveName'];
                $charid = $rows['characterID'];
                $userData = $discord->api('user')->show($discordid);
                $discordname = $userData['username'];
                
                $guildMember = $discord->api("guild")->members()->member($guildID, $discordid);
                $nickName = $guildMember["nick"];
                $changeMemberPath = "/guilds/" . $guildID . "/members/" . $discordid;
                
                if($this->addTicker == 'true')
                {
                   $url = "https://api.eveonline.com/eve/CharacterAffiliation.xml.aspx?ids=$charid";
                   $xml = makeApiRequest($url);

                   if (!$xml->error) {
                      $allianceId = "";
                      foreach ($xml->result->rowset->row as $character) {
                        $allianceId = $character->attributes()->allianceID;
                      }
                      if($allianceId != "")
                      {
                        $crestResult = makeCrestRequest("/alliances/$allianceId/");
                        $eveName = $eveName . " [" . $crestResult->shortName . "]";
                      }
                   }
                }
                if ($discordname != $nickName) {
                   $discord->getHttpClient()->request("PATCH", $changeMemberPath, [
                                'json' => [
                                    'nick' => "" . $eveName
                                ]
                            ]);                            
                    $discord->api("channel")->messages()->create($toDiscordChannel, "Name of Discord user $discordname changed to $eveName to match their in-game name.");
                    $logger->info("Name of Discord user $discordname changed to $eveName to match their in-game name.");
                }



            }
            $logger->info("All users names are correct.");
            return null;

        }
        $logger->info("No users found in database.");
        return null;


    }
    return null;
});