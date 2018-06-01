<?php 
/*
 * Brafton Error Class
 * rewrite for seperate function to get the erros currently logged, add new error function
 */
//for debugging.  Displays 
class BraftonErrorReport {
    
    /*
     *$url Current url location
     */
    private $url;
    /*
     *$e_key Encryption key for verification for the error logging api
     */
    private $e_key;
    /*
     *$post_url Url location for error reporting with $e_key as [GET] Parameter
     */
    private $post_url;
    /*
     *$section Current sectoin reporting the error set by passing variable to the set_section method
     */
    private $section;
    /*
     *$level current brafton level of severity set by passing int variable to the set_level method
     */
    public $level;
    
    private $domain;
    //Construct our error reporting functions
    public function __construct(){
        $this->domain = client;
        $this->api = brafton_apiKey;
        $this->brand = domain;
        $this->e_key = 'hmng2s19skfai1mba9lp5cyb';
        $this->post_url = 'http://updater.brafton.com/errorlog/hubspotcos/error/'.$this->e_key;
        $this->level = 1;
        $this->section = 'error initialize';
        register_shutdown_function(array($this,  'check_for_fatal'));
        set_error_handler(array($this, 'log_error') );
        set_exception_handler(array($this, 'log_exception'));
        ini_set( "display_errors", 1 );
        error_reporting( E_ALL );
        

        
    }
    //Sets the current section reporting the error periodically set by the article and video loops themselves
    public function set_section($sec){
        $this->section = $sec;   
    }
    //sets the current level of error reporting used to determine if remote sending is enabled periodically upgraded during article and video loops from 1 (critical error script stopped running) -> 5 (minor error script continued but something happened.)
    public function set_level($level){
        $this->level = $level;
    }
    //upon error being thrown log_error fires off to throw an exception erro
    public function log_error( $num, $str, $file, $line, $context = null )
    {
        $this->log_exception( new ErrorException( $str, 0, $num, $file, $line ) );
    }
    //workhorse of the error reporting.  This function does the heavy lifting of logging the error and sending an error report
    public function log_exception( Exception $e ){
        if(error_reporting() == 0){
            return;
        }
        if ( ($this->level == 1) ){
    
            $errorlog = array(
                'Domain'    => $this->domain,
                'API'       => $this->api,
                'Brand'     => $this->brand,
                'client_sys_time'  => '',
                'error'     => get_class($e).' :  | '.$e->getMessage().' in '.$e->getFile().' on line '.$e->getLine().' brafton_level '.$this->level.' in section '.$this->section
            );

            $errorlogjson = json_encode($errorlog);
            echo '<pre>';
            var_dump($errorlog);
            echo '</pre>';
            $post_args = array(
                    'error' => $errorlogjson
            );
            //send error to the system
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->post_url);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_args);
            $result = curl_exec($ch);            
            return;
        }
        else{
            return;
        }
        exit();
    }

    //function for checking if fatal error has occured and trigger the error flow
    public function check_for_fatal(){
        $error = error_get_last();
        if ( $error["type"] == E_ERROR )
            $this->log_error( $error["type"], $error["message"], $error["file"], $error["line"] );
    }

}
?>