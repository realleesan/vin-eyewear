<?php
/**
 * Vin Eyewear - Base Controller
 * Parent class for all controllers with view rendering functionality
 */

class BaseController
{
    /**
     * Render a view with data
     * 
     * @param string $viewName View path relative to views directory
     * @param array $data Data to pass to the view
     * @return void
     */
    protected function renderView($viewName, $data = [])
    {
        // Extract data array to individual variables
        extract($data);
        
        // Load the master layout
        require_once VIEWS_PATH . '/_layout/master.php';
    }
}