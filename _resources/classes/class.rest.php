<?php

class RestUtil {
    
    public function getStatusCodeMessage($status) {
        
        // Create http response array
        $http_response_codes=parse_ini_file(ROOT . '_ini/http.status.ini');
        
        // Return the status code ... or return nothing
        return (isset($http_response_codes[$status])) ? $http_response_codes[$status] : ''; 
    }
    
    // Process the request
    public static function processRequest()  
    {  
        // get our verb  
        $request_method = strtolower($_SERVER['REQUEST_METHOD']);  
        $return_obj     = new RestRequest();  
        // we'll store our data here  
        $data= array();  
        
        switch ($request_method)  
        {  
            // gets are easy...  
            case 'get':  
                $data = $_GET;  
                break;  
            // so are posts  
            case 'post':  
                $data = $_POST;  
                break;  
            // here's the tricky bit...  
            case 'put':  
                // basically, we read a string from PHP's special input location,  
                // and then parse it out into an array via parse_str... per the PHP docs:  
                // Parses str  as if it were the query string passed via a URL and sets  
                // variables in the current scope.  
                parse_str(file_get_contents('php://input'), $put_vars);  
                $data = $put_vars;  
                break;  
        }  
      
        // store the method  
        $return_obj->setMethod($request_method);  
      
        // set the raw data, so we can access it if needed (there may be  
        // other pieces to your requests)  
        $return_obj->setRequestVars($data);  
      
        if(isset($data['data']))  
        {  
            // translate the JSON to an Object for use however you want  
            $return_obj->setData(json_decode($data['data']));  
        }  
        return $return_obj;  
    }    
  
    public static function sendResponse($status = 200, $body = '', $content_type = 'text/html')  
    {  
        $status_header = 'HTTP/1.1 ' . $status . ' ' . RestUtil::getStatusCodeMessage($status);  
        // set the status
        header($status_header);  
        // set the content type
        header('Content-type: ' . $content_type);  
      
        // pages with body are easy  
        if($body != '')  
        {  
            // send the body  
            echo $body;  
            exit;  
        }  
        // we need to create the body if none is passed  
        else  
        {  
            // create some body messages  
            $message = '';  
      
            // Request Failed
            switch($status)  
            {
                case 400:
                    $message = 'Your request is empty or invalid. Please follow the guidelines provided in the API documentation';  
                    break;
                case 401:
                    $message = 'You must be authorized to view this page.';  
                    break;
                case 404:
                    $message = 'The requested URL ' . $_SERVER['REQUEST_URI'] . ' was not found.';  
                    break;
                case 406:
                    $message = 'Your request is empty or invalid. Please follow the guidelines provided in the API documentation';  
                    break;
                case 500:
                    $message = 'The server encountered an error processing your request.';  
                    break;  
                case 501:
                    $message = 'The requested method is not implemented.';  
                    break;                
            }
      
            // servers don't always have a signature turned on (this is an apache directive "ServerSignature On")  
            $signature = ($_SERVER['SERVER_SIGNATURE'] == '') ? $_SERVER['SERVER_SOFTWARE'] . ' Server at ' . $_SERVER['SERVER_NAME'] . ' Port ' . $_SERVER['SERVER_PORT'] : $_SERVER['SERVER_SIGNATURE'];  
      
            // this should be templatized in a real-world solution  
            $body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">  
                        <html>  
                            <head>  
                                <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">  
                                <title>' . $status . ' ' . RestUtil::getStatusCodeMessage($status) . '</title>  
                            </head>  
                            <body>  
                                <h1>' . RestUtil::getStatusCodeMessage($status) . '</h1>  
                                <p>' . $message . '</p>  
                                <hr />  
                                <address>' . $signature . '</address>  
                            </body>  
                        </html>';  
      
            echo $body;  
            exit;  
        }  
    }  
    
}

class RestRequest {
    private $request_vars;  
    private $data;  
    private $http_accept;  
    private $method;  
  
    public function __construct()  
    {  
        $this->request_vars      = array();  
        $this->data              = '';  
        $this->http_accept       = (strpos($_SERVER['HTTP_ACCEPT'], 'json')) ? 'json' : 'xml';  
        $this->method            = 'get';  
    }
    
    public function setData($data)  
    {  
        $this->data = $data;  
    }  
  
    public function setMethod($method)  
    {  
        $this->method = $method;  
    }  
  
    public function setRequestVars($request_vars)  
    {  
        $this->request_vars = $request_vars;  
    }  
  
    public function getData()  
    {  
        return $this->data;  
    }  
  
    public function getMethod()  
    {  
        return $this->method;  
    }  
  
    public function getHttpAccept()  
    {  
        return $this->http_accept;  
    }  
  
    public function getRequestVars()  
    {  
        return $this->request_vars;  
    }  
}