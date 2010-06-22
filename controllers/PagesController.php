<?php
/**
 * There's a few changes here. First the Router has been changed for the view method. 
 * Second, Pages now uses a model and can connect to a datasource, this is simply for organization and convention.
 * Minerva aims to use terminology that most people can relate to (that would include non-programmers too).
 * Therefore, a "page" in Minerva is exactly what one would expect a "web page" to be. To a developer, that could mean it accesses 
 * a database and it could just mean that it displays a static file with php/html/css/js code within it served from the disk.
 * 
 * The Page model still does not need a database connection to work for viewing static pages, but other methods may.
 * So in turn, by proxy, some methods within this controller require/use a database. The "view" method, however, does not.
 * It remains, roughly, the same as it does out of the box with Lithium. The major change being all "static" files organized 
 * into a new "static" folder. This helps to keep the static view templates separate from the dynamic view templates for pages.
 *
 * The normal convention of "index', "add", "edit" and "delete" are changed a little to closer represent the acronym CRUD.
 * The methods are now "index", "create", "read", "update", and "delete". This distinguishes the "view" from the "read" method. 
 * Think of it as "you are viewing page from disk" or "you are reading a page from a datasource." (forget for a second that it 
 * could be cached to disk and you're really reading from disk, this class doesn't know that).
 *
 */
namespace app\controllers;
use app\models\Page;
use \lithium\util\Set;

class PagesController extends \lithium\action\Controller {
		
	/**
	 * The default method here is changed. First off, the Router class now uses this view method if the URL is /page/{:args}
	 * It changes the URL convention from pluralized controller, but since we're talking about static pages, I felt that was ok.
	 * Especially since URLs are for humans first and foremost.
	 * "/pages/view/home" still works if needed to be used in array fashion like the Html helper's link method.
	 * This leaves us in need of a new method though that returns dynamic pages from a datasource. That's the "read" method below.
	 *
	*/
	public function view() {		
		if (empty($path)) {
			$path = array('static', 'home');
		} else {
			$path = array('static', func_get_args());
		}		
		$this->render(join('/', $path));
	}	
	
	/**
         * Index listing method responsible for showing lists of pages with pagination options.
         * If $library is passed and the library has a Page model, it will be instantiated. Additional filters can be found there.
         * Among other things, it's a good place to have a filter change the find query to only show pages using that library.
	*/
	public function index($library=null) {
		// If we are using a library, instantiate it's Page model (bridge from plugin to core)
		if((isset($library)) && ($library != 'app') && (!empty($library))) {		
			// Just instantiating the library's Page model will essentially "bridge" and extend the main app's Page model	
			$class = '\app\libraries\\'.$library.'\models\Page'; 	  		
			if(class_exists($class)) {
                            $Library = new $class();
                        }
		}
		
		// Default options for pagination, merge with URL parameters
		$defaults = array('page' => 1, 'limit' => 10, 'order' => array('descending' => 'true'));
		$params = Set::merge($defaults, $this->request->params);
		if((isset($params['page'])) && ($params['page'] == 0)) { $params['page'] = 1; }
		list($limit, $page, $order) = array($params['limit'], $params['page'], $params['order']);
		
		$records = Page::find('all', array(
			'limit' => $params['limit'],
			'offset' => ($params['page'] - 1) * $params['limit'], // TODO: "offset" becomes "page" soon or already in some branch...
			//'order' => $params['order']
			'order' => array('_id' => 'asc')			
		));	
		$total = Page::count();
		
		$this->set(compact('records', 'limit', 'page', 'total'));
	}

	/**
	 * Create a page. The "library" decides which library to use when creating the page (optional).
	 * A library can change the fields displayed in the form so that different data can be saved to the page among other things.
	 * 
	 * A "library" (or plugin) can be thought of like a "content type" in Drupal, but much more too. It's on steroids.
	 * Even more insane, we can bundle these as phars so that distribution even easier. 
	 * Since it's so modular and transportable...an online registry of libraries can be created so the CMS can browse
	 * and download at will additional libraries that will extend the CMS.
	 *
	*/
	public function create($library=null) {	
            // If we are using a library, instantiate it's Page model (bridge from plugin to core)
            if((isset($library)) && ($library != 'app') && (!empty($library))) {		
		// Just instantiating the library's Page model will essentially "bridge" and extend the main app's Page model	
		$class = '\app\libraries\\'.$library.'\models\Page'; 	  		
		if(class_exists($class)) {
                    $Library = new $class();
                }			
            }	
	
            // Get the fields so the view template can iterate through them and build the form
            $fields = Page::$fields;
	
            // Save
            if ($this->request->data) {
                $this->request->data['library'] = $library; // Set the library to be saved with the record, saving null is ok too
                
                $page = Page::create($this->request->data);
                    if($page->save()) {				
                            $this->redirect(array('controller' => 'pages', 'action' => 'index'));
                    }
            }
            
            if(empty($page)) {                
                $page = Page::create(); // Create an empty page object
            }
            
            $this->set(compact('page', 'fields'));
        }
	
	/**
	 * Update a page.
	 *
	*/
	public function update($url=null) {	
		// First, get the record
		$record = Page::find('first', array('conditions' => array('url' => $url)));
		
		// Next, if the record uses a library, instantiate it's Page model (bridge from plugin to core)
		if((isset($record->library)) && ($record->library != 'app') && (!empty($record->library))) {		
			// Just instantiating the library's Page model will essentially "bridge" and extend the main app's Page model	
			$class = '\app\libraries\\'.$record->library.'\models\Page'; 	  		
			if(class_exists($class)) {
                            $Library = new $class();
                        }
			// var_dump(Page::$fields); // debug
			// var_dump($Library::$fields); // just the extended library's fields			
		}
		
		// Get the fields (may include more now from the plugin) for the view and add primary key and library (hidden fields)
		$fields = Page::$fields;
		$fields[Page::key()] = array('type' => 'hidden', 'label' => false); // set the key...so we can update
		$fields['library'] = array('type' => 'hidden', 'label' => false); 
					
		// Update the record
		if ($this->request->data) {
                        // Call save from the main app's Page model
                        if($record->save($this->request->data)) {
                            $this->redirect(array('controller' => 'pages', 'action' => 'index'));
                        }                        
		}
		
                $this->set(compact('record', 'fields'));
	}

	/**
	 * Read a page (like "view()" but retrieves page data from the database).
	 * Also, like other methods, extra data is bridged in from an optional associated library on the record itself.
	 *
	*/
	public function read($url) {
	    // get the page record (also within this record contains the library used, which is important)
	    $record = Page::find('first', array('conditions' => array('url' => $url)));
            if((isset($record->library)) && ($record->library != 'app') && (!empty($record->library))) {
		// Just instantiating the library's Page model will essentially "bridge" and extend the main app's Page model	
                $class = '\app\libraries\\'.$record->library.'\models\Page'; 	  		
		if(class_exists($class)) {
                    $Library = new $class();
                }
            } 	
            $this->set(compact('record'));
	}
	
	/** 
	 *  Delete a page record.
	 *  Plugins can apply filters within their Page model class in order to run filters for the delete.
	 *  Useful for "clean up" tasks such as removing image files from the server if the plugin was a gallery for example.
	*/
	public function delete($url=null) {
		if(!$url) {
			$this->redirect(array('controller' => 'pages', 'action' => 'index'));
		}
                
                // Instantiate the library's model if one was used
		$record = Page::find('first', array('conditions' => array('url' => $url)));		
                if((isset($record->library)) && ($record->library != 'app') && (!empty($record->library))) {	  		
			$class = '\app\libraries\\'.$record->library.'\models\Page'; 	  		
			if(class_exists($class)) {
                            $Library = new $class();
                        }
		}
		
                // TODO: should messages like this be done with a filter on delete??
		// Delete the record TODO: put in some kinda flash messages (like cake has) to notify the user things deleted or didn't
		if($record->delete()) {
			$this->redirect(array('controller' => 'pages', 'action' => 'index'));
		} else {
			$this->redirect(array('controller' => 'pages', 'action' => 'index'));
		}		
	}
    
}
?>