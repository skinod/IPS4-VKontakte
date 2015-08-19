<?php
/**
 * @brief		VK Login Handler
 * @author		<a href='http://www.skinod.com'>Skinod</a>
 * @copyright	(c) 2015 skinod.com
 */

namespace IPS\Login;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * VK Login Handler
 */
class _VK extends LoginAbstract
{
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'vk';
	
	/**
	 * Get Form
	 *
	 * @param	\IPS\Http\Url	$url	The URL for the login page
	 * @return	string
	 */
	public function loginForm( $url, $ucp=FALSE )
	{		
		if ( $ucp )
		{
			$state = "ucp-" . \IPS\Session::i()->csrfKey;
		}
		else
		{
			$state = \IPS\Dispatcher::i()->controllerLocation . "-" . \IPS\Session::i()->csrfKey;
		}

		$url = \IPS\Http\Url::internal( 'applications/core/interface/vk/auth.php', 'none');

		$scope = 'offline,email';

		// if ( \IPS\Settings::i()->profile_comments )
		// {
			// $scope .= ',status';
		// }

		return \IPS\Theme::i()->getTemplate( 'plugins', 'core', 'global' )->sodvk( "https://oauth.vk.com/authorize?client_id={$this->settings['app_id']}&amp;scope={$scope}&amp;redirect_uri=" . urlencode( $url ) . "&amp;state={$state}"  );
	}
	
	/**
	 * Authenticate
	 *
	 * @param	string			$url	The URL for the login page
	 * @param	\IPS\Member		$member	If we want to integrate this login method with an existing member, provide the member object
	 * @return	\IPS\Member
	 * @throws	\IPS\Login\Exception
	 */
	public function authenticate( $url, $member=NULL )
	{
		$url = $url->setQueryString( 'loginProcess', 'vk' );

		try
		{
			/* CSRF Check */
			if ( \IPS\Request::i()->state !== \IPS\Session::i()->csrfKey )
			{
				throw new \IPS\Login\Exception( 'CSRF_FAIL', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			if(isset(\IPS\Request::i()->error) || !isset(\IPS\Request::i()->code)) {
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}
			
			/* Get a token */
			try
			{
				$response = \IPS\Http\Url::external( "https://oauth.vk.com/access_token" )->setQueryString( array(
					'client_id'		=> $this->settings['app_id'],
					'redirect_uri'	=> (string) \IPS\Http\Url::internal( 'applications/core/interface/vk/auth.php', 'none' ),
					'client_secret'	=> $this->settings['app_secret'],
					'code'			=> \IPS\Request::i()->code
				) )->request()->Get()->decodeJson();

				if(isset($response['error'])) {
					throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
				}
			}
			catch( \RuntimeException $e )
			{
				throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
			}

			/* Get the user data */
			$userData = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$response['user_id']}&access_token={$response['access_token']}&fields=first_name,last_name,screen_name,bdate,nickname" )->request()->get()->decodeJson();
			$userData = $userData['response'][0];

   			/* Find or create member */
   			$newMember = FALSE;
   			if ( $member === NULL )
   			{
				$member = \IPS\Member::load( $response['user_id'], 'vk_id' );
				if ( !$member->member_id )
				{
					if(isset($response['email'])) {
						$existingEmail = \IPS\Member::load( $response['email'], 'email' );
						if ( $existingEmail->member_id )
						{
							$exception = new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::MERGE_SOCIAL_ACCOUNT );
							$exception->handler = 'vk';
							$exception->member = $existingEmail;
							$exception->details = array($response['access_token'], $response['user_id']);
							throw $exception;
						}
					}
					
					$member = new \IPS\Member;
					if ( \IPS\Settings::i()->reg_auth_type == 'admin' or \IPS\Settings::i()->reg_auth_type == 'admin_user' )
					{
						$member->members_bitoptions['validating'] = TRUE;
					}
					$member->member_group_id = \IPS\Settings::i()->member_group;
					$member->email = isset($response['email'])?$response['email']:'';

					$member->name = $userData['nickname'];

					if ( empty($member->name) AND $this->settings['real_name'] )
					{
						$name = $userData['first_name'] . ' ' . $userData['last_name'];
						$existingUsername = \IPS\Member::load( $name, 'name' );
						
						if ( !$existingUsername->member_id )
						{
							$member->name = $name;
						}
					}
					$member->profilesync = json_encode( array( 'VK' => array( 'photo' => TRUE, 'status' => '' ) ) );
					$newMember = TRUE;
				}
			}

			/* Update details */
			$member->vk_id = $response['user_id'];
			$member->vk_token = $response['access_token'];
			$member->save();
			
			/* Sync */
			if ( $newMember )
			{
				if ( \IPS\Settings::i()->reg_auth_type == 'admin_user' )
				{
					\IPS\Db::i()->update( 'core_validating', array( 'user_verified' => 1 ), array( 'member_id=?', $member->member_id ) );
				}
				
				$sync = new \IPS\core\ProfileSync\VK( $member );
				$sync->sync();
			}
			
			/* Return */
			return $member;
   		}
   		catch ( \IPS\Http\Request\Exception $e )
   		{
	   		throw new \IPS\Login\Exception( 'generic_error', \IPS\Login\Exception::INTERNAL_ERROR );
   		}
	}

	/**
	 * Link Account
	 *
	 * @param	\IPS\Member	$member		The member
	 * @param	mixed		$details	Details as they were passed to the exception thrown in authenticate()
	 * @return	void
	 */
	public static function link( \IPS\Member $member, $details )
	{
		try {
			$userData = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$details[1]}&access_token={$details[0]}&fields=first_name,last_name,screen_name,bdate,nickname" )->request()->get()->decodeJson();
			$userData = $userData['response'][0];
			$member->vk_id = $details[1];
			$member->vk_token = $details[0];
			$member->save();
		}catch(\Exception $e) {}
	}

	/**
	 * ACP Settings Form
	 *
	 * @param	string	$url	URL to redirect user to after successful submission
	 * @return	array	List of settings to save - settings will be stored to core_login_handlers.login_settings DB field
	 * @code
	 	return array( 'savekey'	=> new \IPS\Helpers\Form\[Type]( ... ), ... );
	 * @endcode
	 */
	public function acpForm()
	{
		// \IPS\Output::i()->sidebar['actions'] = array(
		// 	'help'	=> array(
		// 		'title'		=> 'help',
		// 		'icon'		=> 'question-circle',
		// 		'link'		=> \IPS\Http\Url::external( 'http://skinod.com/' ),
		// 		'target'	=> '_blank',
		// 		'class'		=> ''
		// 	),
		// );
		
		return array(
			'app_id'		=> new \IPS\Helpers\Form\Text( 'login_vk_app', ( isset( $this->settings['app_id'] ) ) ? $this->settings['app_id'] : '', TRUE ),
			'app_secret'	=> new \IPS\Helpers\Form\Text( 'login_vk_secret', ( isset( $this->settings['app_secret'] ) ) ? $this->settings['app_secret'] : '', TRUE ),
			'real_name'		=> new \IPS\Helpers\Form\YesNo( 'login_real_name', ( isset( $this->settings['real_name'] ) ) ? $this->settings['real_name'] : FALSE, TRUE )
		);
	}
	
	/**
	 * Test Settings
	 *
	 * @return	bool
	 * @throws	\InvalidArgumentException
	 */
	public function testSettings()
	{
		return TRUE;
	}
	
	/**
	 * Can a member change their email/password with this login handler?
	 *
	 * @param	string		$type	'email' or 'password'
	 * @param	\IPS\Member	$member	The member
	 * @return	bool
	 */
	public function canChange( $type, \IPS\Member $member )
	{
		return FALSE;
	}
}