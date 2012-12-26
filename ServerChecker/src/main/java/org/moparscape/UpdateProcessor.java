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

public class UpdateProcessor extends OnlineOfflineProcessor {

    public UpdateProcessor(int millisPerRow, int millisTimeLimit) {
        super(millisPerRow, millisTimeLimit,
                "UPDATE servers SET online=1, totalcount=totalcount+1, oncount=oncount+1, `uptime` = (oncount / totalcount * 100) WHERE sponsored = '0' AND id IN ",
                "UPDATE servers SET online=0, totalcount=totalcount+1, `uptime` = (oncount / totalcount * 100) WHERE sponsored = '0' AND id IN ",
                "servers");
    }

    @Override
    public void online(String resolvedIP, Long id, String hostName) {
        super.online(resolvedIP, id, hostName);
        online.add(id);
    }

    @Override
    public void offline(Long id) {
        super.offline(id);
        offline.add(id);
    }

    public void finish() {
        finalQuery = "DELETE FROM `servers` WHERE `uptime` < '40'";
        super.finish();
    }

}
