/*
 * MoparScape.org server status page
 * Copyright (C) 2012  Travis Burtrum (moparisthebest)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

package org.moparscape;

public class NewProcessor extends OnlineOfflineProcessor {

    public NewProcessor(int millisPerRow, int millisTimeLimit) {
        super(millisPerRow, millisTimeLimit,
                "INSERT INTO `servers` (`uid`, `uname`, `name`, `ip`, `port`, `version`, `time`, `info`, `ipaddress`, `rs_name`, `rs_pass`) SELECT `uid`, `uname`, `name`, `ip`, `port`, `version`, `time`, `info`, `ipaddress`, `rs_name`, `rs_pass` FROM `toadd` WHERE id IN ",
                "DELETE FROM `toadd` WHERE id IN ",
                "toadd");
    }

    @Override
    public void online(String resolvedIP, Long id, String hostName) {
        super.online(resolvedIP, id, hostName);
        online.add(id);
        offline.add(id);
    }

    public void finish() {
        // delete entries that are past a certain date old
        // System.currentTimeMillis()/1000 is unix timestamp
        // 86400 is 24 hours in seconds
        finalQuery = String.format("DELETE FROM `toadd` WHERE `time` < '%d'", (System.currentTimeMillis() / 1000) - 86400);
        super.finish();
    }
}
