<?php

/*
 * Smarty plugin
 * -------------------------------------------------------------
 * File:     function.submission_listing_quicklinks
 * Type:     function
 * -------------------------------------------------------------
 */
function smarty_function_submission_listing_quicklinks($params, &$smarty)
{
    echo ft_display_submission_listing_quicklinks($params["context"], $smarty);
}
