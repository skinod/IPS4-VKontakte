<?php
/**
 * @brief		VK Login Handler Redirect URI Handler
 * @author		<a href='http://www.skinod.com'>Skinod</a>
 * @copyright	(c) 2015 skinod.com
 */

require_once str_replace( 'applications/core/interface/vk/auth.php', '', str_replace( '\\', '/', __FILE__ ) ) . 'init.php';
$state = explode( '-', \IPS\Request::i()->state );
if ( $state[0] == 'ucp' )
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=settings&area=profilesync&service=VK&loginProcess=VK&state={$state[1]}&code=" . urlencode( \IPS\Request::i()->code ), 'front', 'settings_VK' ) );
}
else
{
	\IPS\Output::i()->redirect( \IPS\Http\Url::internal( "app=core&module=system&controller=login&loginProcess=VK&state={$state[1]}&code=" . urlencode( \IPS\Request::i()->code ), $state[0] ) );
}