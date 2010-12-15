<?php
/*
 * A general utility class for familyspoon.com. This includes several useful methods used throughout the site.
*/
namespace minerva\util;

use \RecursiveIteratorIterator;
use \RecursiveArrayIterator;

class Util {
    
    /*
     * in_array recursive function using Spl libraries. Quite useful.
    */
    public function in_array_recursive($needle=null, $haystack=null) {
        if((empty($needle)) || (empty($haystack))) {
            return false;
        }
        $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack)); 
        foreach($it AS $element) {
            if($element === $needle) {
                return true;
            } 
        }
        return false;
    }
    
    /*
     * A simple method to return a unique string, useful for approval codes and such.
     * An md5 hash of the unique id will be 32 characters long and the sha1 will be 40 characters long.
     * Without hashing, the unique id will be 13 characters long and 23 long if more entropy is used.
     * 
     * @params $options Array
     *      - hash: The hash method to use to hash the uid, md5, sha1, or false (default is md5)
     *      - prefix: The prefix to use for uniqid() method
     *      - entropy: Boolean, whether or not to add additional entropy (more unique)
    */
    public function unique_string($options=array()) {
        $options += array('hash' => 'md5', 'prefix' => '', 'entropy' => false);
        switch($options['hash']) {
            case 'md5':
                return md5(uniqid($options['prefix'], $options['entropy']));
            default:
            break;
            case 'sha1':
                return sha1(uniqid($options['prefix'], $options['entropy']));
            break;
            case false:
                return uniqid($options['prefix'], $options['entropy']);
            break;
        }
    }
    
    /**
     * Generate a unique pretty url for the model's record.
     * 
     * @params $options Array
     *      - url: The requested url (typically the inflector::slug() for a title)
     *      - id: The current id (optional, only if editing a record, so it knows to exclude itself as a conflict)
     *      - model: The model that's used as the lookup (to run the find() on)
     *      - separator: The optional separator symbol for spaces (default: -)
     * @return String The unique pretty url.
    */
    public function unique_url($options=array()) {
        $options += array('url' => null, 'id' => null, 'model' => null, 'separator' => '-');
        if((!$options['url']) || (!$options['model'])) {
            return null;
        }        
        $records = $options['model']::find('all', array('fields' => array('url'), 'conditions' => array('url' => array('like' => '/'.$options['url'].'/'))));
        $conflicts = array();
        
        foreach($records as $record) {
            // If the record id is an object, it's probably a MongoId, so make it a string to compare IF the passed id was not an object too.
            if((is_object($record->{$options['model']::key()})) && (!is_object($options['id']))) {
                $record_id = (string)$record->{$options['model']::key()};
            } else {
                $record_id = $record->{$options['model']::key()};
            }
            if($record_id != $options['id']) {
                $conflicts[] = $record->url;
            }
        }
        
        if (!empty($conflicts)) {
                $firstSlug = $options['url'];
                $i = 1;
                while($i > 0) {                        
                        if (!in_array($firstSlug . $options['separator'] . $i, $conflicts)) {					
                                $options['url'] = $firstSlug . $options['separator'] . $i;
                                $i = -1;
                        }
                $i++;
                }
        }        
        return strtolower($options['url']);
    }
}
?>