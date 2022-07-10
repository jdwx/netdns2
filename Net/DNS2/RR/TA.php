<?php
declare( strict_types = 1 );

/**
 * DNS Library for handling lookups and updates. 
 *
 * Copyright (c) 2020, Mike Pultz <mike@mikepultz.com>. All rights reserved.
 *
 * See LICENSE for more details.
 *
 * @category  Networking
 * @package   Net_DNS2
 * @author    Mike Pultz <mike@mikepultz.com>
 * @copyright 2020 Mike Pultz <mike@mikepultz.com>
 * @license   http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link      https://netdns2.com/
 * @since     File available since Release 1.2.0
 *
 */

/**
 * The TA RR is implemented exactly like the DS record, so
 * for now we just extend the DS RR and use it.
 *
 */
class Net_DNS2_RR_TA extends Net_DNS2_RR_DS
{
}
