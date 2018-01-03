<?php
/**
 * Akeeba Release Maker
 * An automated script to upload and release a new version of an Akeeba component.
 * Copyright (c)2006-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

class ArmUtilsString
{
	public static function toSlug($value)
	{
		//remove any '-' from the string they will be used as concatonater
		$value = str_replace('-', ' ', $value);
		
		//convert to ascii characters
		$value = self::toASCII($value);
		
		//lowercase and trim
		$value = trim(strtolower($value));
		
		//remove any duplicate whitespace, and ensure all characters are alphanumeric
		$value = preg_replace(array('/\s+/','/[^A-Za-z0-9\-]/'), array('-',''), $value);
		
		//limit length
		if (strlen($value) > 100) {
			$value = substr($value, 0, 100);
		}
		
		return $value;
	}
	
	public static function toASCII($value)
	{
		$string = htmlentities(utf8_decode($value));
		$string = preg_replace(
			array('/&szlig;/','/&(..)lig;/', '/&([aouAOU])uml;/','/&(.)[^;]*;/'),
			array('ss',"$1","$1".'e',"$1"),
			$string);

		return $string;
	}
}
