<?php
/**
 * Created by PhpStorm.
 * User: jitendra
 * Date: 1/15/18
 * Time: 11:09 AM
 */
if ( ! defined('BASEPATH')) exit("No direct script access allowed");


class MY_Benchmark extends CI_Benchmark
{
    function __construct()
    {
        spl_autoload_extensions('.php');
        spl_autoload_register( function( $trait )
        {

            $Traits_folder  = "traits/";
            $trait          =  str_replace( "\\", "/", $trait ) ;

            if ( !mb_ereg( "^func/", $trait ) )
                return;
            if ( !file_exists( APPPATH . $Traits_folder . $trait . ".php" ) )
            {
                $trace  = debug_backtrace();
                _error_handler( E_ERROR, "Trait '{$trait}' not found", $trace[1]['file'], $trace[1]['line'] );
                exit();
            }
            #spl_autoload( APPPATH . $Traits_folder . $trait );
            include_once( APPPATH . $Traits_folder . $trait. '.php');
        });
    }
}
/* End of file MY_Benchmark.php */
/* Location: ./application/core/MY_Benchmark.php */

