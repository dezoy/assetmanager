<?php
/**
 * This is what MODX calls our "base controller". Per MODx parlance, this file must reside in the 
 * directory defined as the namespace's core path.  After that tip-of-the-hat politeness to the MODX
 * "rules", we head off the map on our own custom path: all manager requests to our own classes (see 
 * the BaseController.php).
 *
 * ARCHITECTURE: 
 *
 * The Page controller generates HTML pages whereas all other controllers generate JSON responses,
 * whereas all others (which extend the APIController) are meant for API interactions only (e.g. post
 * back data etc)
 *
 * This is not a textbook example, but the goal is to have a mostly REST based interface for easier
 * testing. (as opposed to JSON responses generated by the other controllers).  The reason is 
 * testability: most of the manager app can be tested by $scriptProperties in, JSON out.  The HTML 
 * pages generated by this controller end up being static HTML pages (well... ideally, anyway). 
 *
 * See http://stackoverflow.com/questions/10941249/separate-rest-json-api-server-and-client 
 *
 * FUTURE:
 * Static pages. A manager page gets a JSON variable generated for it (from an API request)
 * containing all the data it needs: e.g. a record (e.g. a Field record) or for a product, 
 * it would include all related data in whatever detail the request requires. 
 * 
 * JSON data should be formatted in *exactly* the format that it should be 
 * submitted in (garbage in, garbage out: no sleight of hand or restructuring of the JSON or twerking 
 * by the indexedToRecordset() function after submission).
 *
 * The HTML page should include the appropriate Javascript to populate the form and format any records
 * (e.g. using Handlebars). Dynamic editing of any parts of the data should not be concerned about 
 * the _names_ of the HTML field elements: instead all manipulations should change the source JSON 
 * directly, e.g. 
 *      assman.product.asset[asset_id].title = "new title" 
 *      instead of:
 *      jQuery('#arbitrary_label_'+asset_id).val("new title")
 *
 * The trickiest part about this is handling the custom fields and the fact that they trigger a form element to 
 * be generated.  It's like a snake eating its own tail...
 *
 * JSON REST API
 * 
 * Responses follow jSend guidelines: http://labs.omniti.com/labs/jsend
 *
 * ROUTING:
 *
 * I'm overriding a fair number of the modExtraManagerController functions there to support
 * custom routing.  By overriding the getInstance() method I am abandoning 
 * the somewhat limited MODX convention of mapping the &action URL parameter to a controller class
 * and instead I'm organizing requests as follows:
 *
 *  &class = classname of the controller class. 
 *  &method = base name of the method being called, default is "index"
 *
 * If POST data is detected, "post" is prepended to the method name; otherwise "get" is prepended.
 * Thus the mapping behaves like this:
 *
 * URL: /index.php?a=xxx&class=product&method=find   
 *      maps to :                       ProductController->getFind() 
 *      OR if $_POST data is present :  ProductController->postFind()  
 *
 * This allows for cleaner classnames and the ability to support dynamic routing and 404s.
 *
 * @package assman
 */
 
// Gotta do this here because we don't have a reliable event for this. 
require_once dirname(__FILE__) .'/vendor/autoload.php';
class IndexManagerController extends \Assman\BaseController {

    /**
     * This acts as a class loader.  Beware the difficulties with testing with the "new" keyword!!!
     * See composer.json's autoload section: Controller classes should be found in the controllers/ directory
     * We ignore the incoming $className here and instead fallback to our own mapping which follows the 
     * pattern : \assman\{$Controller_Class_Slug}Controller
     * We can't override the Base controller constructor because this loops back onto it.
     *
     * @param object \modX instance
     * @param string $className (ignored, instead we look to $_REQUEST['class'])
     * @param array array config
     * @return instance of a controller object
     */
    public static function getInstance(\modX &$modx, $className, array $config = array()) {

        $config['method'] = (isset($_REQUEST['method'])) ? $_REQUEST['method'] : 'index';
        $class = (isset($_REQUEST['class'])) ? $_REQUEST['class'] : 'Page'; // Default Controller
        
        if (!is_scalar($class)) {
            throw new \Exception('Invalid data type for class');
        }

        $config['controller_url'] = self::url();
        $config['core_path'] = $modx->getOption('assman.core_path', null, MODX_CORE_PATH.'components/assman/');
        $config['assets_url'] = $modx->getOption('assman.assets_url', null, MODX_ASSETS_URL.'components/assman/');

        // If you don't do this, the $_POST array will seem to be populated even during normal GET requests.
        unset($_POST['HTTP_MODAUTH']);
        // Function names are not case sensitive
        if ($_FILES || !empty($_POST)) {
            unset($_POST['_assman']);
            $config['method'] = 'post'.ucfirst($config['method']);
        }
        else {
            $config['method'] = 'get'.ucfirst($config['method']);
        }
        // Classnames are not case-sensitive, but since it triggers the autoloader,
        // we need to manipulate it because some environments are case-sensitive.
        $class = '\\Assman\\'.ucfirst(strtolower($class)).'Controller';

        // Override on error
        if (!class_exists($class)) {
            $modx->log(\modX::LOG_LEVEL_ERROR,'[assman] class not found: '.$class,'',__FUNCTION__,__FILE__,__LINE__);            
            $class = '\\Assman\\ErrorController';
            $config['method'] = 'get404';
        }

        $modx->log(\modX::LOG_LEVEL_INFO,'[assman] Instantiating '.$class.' with config '.print_r($config,true),'',__FUNCTION__,__FILE__,__LINE__);
        
        // See Base::render() for how requests get handled.  
        return new $class($modx,$config);

    }
}
/*EOF*/
