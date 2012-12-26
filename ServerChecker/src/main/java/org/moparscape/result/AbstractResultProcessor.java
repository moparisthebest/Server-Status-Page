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

package org.moparscape.result;

public abstract class AbstractResultProcessor implements ResultProcessor {

    protected final int millisPerRow;
    protected final int millisTimeLimit;

    protected AbstractResultProcessor() {
        // 8 seconds
        millisPerRow = 8 * 1000;
        // 10 minutes
        millisTimeLimit = 10 * 60 * 1000;
    }

    protected AbstractResultProcessor(int millisPerRow, int millisTimeLimit) {
        this.millisPerRow = millisPerRow;
        this.millisTimeLimit = millisTimeLimit;
    }

    public int getMillisTimeLimit() {
        return millisTimeLimit;
    }

    public int getMillisPerRow() {
        return millisPerRow;
    }

}
