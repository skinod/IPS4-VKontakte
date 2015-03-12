<?php
/**
 * @brief		VK Profile Sync
 * @author		<a href='http://www.skinod.com'>Skinod</a>
 * @copyright	(c) 2015 skinod.com
 */

namespace IPS\core\ProfileSync;

/* To prevent PHP errors (extending class does not exist) revealing path */
if ( !defined( '\IPS\SUITE_UNIQUE_KEY' ) )
{
	header( ( isset( $_SERVER['SERVER_PROTOCOL'] ) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0' ) . ' 403 Forbidden' );
	exit;
}

/**
 * VK Profile Sync
 */
class _VK extends ProfileSyncAbstract
{
	/** 
	 * @brief	Login handler key
	 */
	public static $loginKey = 'VK';
	
	/** 
	 * @brief	Icon
	 */
	public static $icon = 'vk';
			
	/**
	 * Is connected?
	 *
	 * @return	bool
	 */
	public function connected()
	{
		return ( $this->member->vk_id and $this->member->vk_token );
	}

	/**
	 * Get user data
	 *
	 * @return	array
	 */
	protected function user()
	{
		if ( $this->user === NULL and $this->member->vk_token )
		{
			try
			{
				$response = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$this->member->vk_id}&access_token={$this->member->vk_token}" )->request()->get()->decodeJson();

				if ( isset( $response['error'] ) )
				{
					throw new \Exception;
				}
			}
			catch ( \IPS\Http\Request\Exception $e )
			{
				$this->member->vk_token = NULL;
				$this->member->save();
			}
		}
		
		return $this->user;
	}

		
	/**
	 * Get photo
	 *
	 * @return	\IPS\Http\Url|null
	 */
	public function photo()
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$this->member->vk_id}&access_token={$this->member->vk_token}&fields=photo_max_orig" )->request()->get()->decodeJson();


			if( !isset($response['response']) OR !isset($response['response'][0]) OR !isset($response['response'][0]['photo_max_orig']) OR \strpos($response['response'][0]['photo_max_orig'], 'camera_a.gif') !== false) {
				return NULL;
			}

			try
			{
				return \IPS\Http\Url::external($response['response'][0]['photo_max_orig']);
			}
			catch (\Exception $e) {}
				
			return NULL;
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Get name
	 *
	 * @return	string
	 */
	public function name()
	{
		try
		{
			$response = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$this->member->vk_id}&access_token={$this->member->vk_token}" )->request()->get()->decodeJson();

			if ( isset( $response["response"] ) && isset($response['response'][0]) )
			{
				return $response['response'][0]['first_name'] . ' ' . $response['response'][0]['last_name'];
			}
		}
		catch ( \IPS\Http\Request\Exception $e )
		{
			return NULL;
		}
	}
	
	/**
	 * Get status
	 *
	 * @return	\IPS\core\Statuses\Status|null
	 */
	// public function status()
	// {
	// 	try
	// 	{
	// 		$response = \IPS\Http\Url::external( "https://api.vk.com/method/getProfiles?uid={$this->member->vk_id}&access_token={$this->member->vk_token}&fields=status" )->request()->get()->decodeJson();
	// 		if ( !empty( $response['data'] ) )
	// 		{				
	// 			$statusData = array_shift( $response['data'] );
				
	// 			$status = \IPS\core\Statuses\Status::createItem( $this->member, $this->member->ip_address, new \IPS\DateTime( $statusData['updated_time'] ) );
	// 			$status->content = nl2br( $statusData['message'], FALSE );
	// 			return $status;
	// 		}
	// 	}
	// 	catch ( \IPS\Http\Request\Exception $e ) { }
	// 	return NULL;
	// }
		
	/**
	 * Disassociate
	 *
	 * @return	void
	 */
	protected function _disassociate()
	{
		$this->member->vk_id = 0;
		$this->member->vk_token = NULL;
		$this->member->save();
	}
}