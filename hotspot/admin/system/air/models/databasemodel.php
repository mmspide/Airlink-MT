<?php if (!defined('BASEPATH')) exit('No direct script access allowed'); 
/**
 * Database
 *
 * Database Model
 *
 * @package		database
 * @author		Sartonice
 * @version		1.0
 * @based on	Airlink
 * @license		GPL/GNU License Copyright (c) 
 */
Class Databasemodel extends CI_Model
{
	function __construct()
	{
		parent::__construct();
		
		//table name
		$this->_table='voucher_list';
		$this->_table_voucher = 'voucher';
		$this->_table_radcheck = 'radcheck';
		$this->_table_radreply = 'radreply';
		$this->_table_usergroup = 'radusergroup';
		$this->_table_radacct = 'radacct';
		
		$this->load->model(array('billingplanmodel','siteconfigmodel','jsonmodel','gologinmodel'));
		$this->load->helper(array('randomuser','number'));

		$this->load->library('globals');
		
		// Ajax
		$this->sTable = "voucher_list";
		$this->aColumns = array('username', 'billingplan', 'valid_until', 'time_used', 'time_remain', 'packet_used', 'packet_remain', 'valid','userprofile','start_time','encryption','money');
	}	
	
	function database_import($post_data)
	{
	
		$billingplan_name = $post_data['billingplan'];
		$billingplan_detail = $this->billingplanmodel->getBillingPlan(null,null,array('name'=>$billingplan_name));

		if($billingplan_detail->num_rows()>0){
			$billingplan=$billingplan_detail->row();

			//โหลดค่า ทั่วไป
			$global = $this->siteconfigmodel->getConfig('global_config');
			$val = $this->session->_unserialize($global->value);

			//insert to database
			$groupname = $billingplan->groupname;
			$name = $billingplan->name;			
			$profile = $billingplan->profile;
			$value = $billingplan->amount;
			$amount = $billingplan->amount;
			$valid_for = $billingplan->valid_for;
			$IdleTimeout = $billingplan->IdleTimeout;
			$redirect_url = $billingplan->redirect_url;	
			$bw_upload = $billingplan->bw_upload;
			$bw_download = $billingplan->bw_download;			
			
			if($this->usermodel->VoucherExist($post_data['username']))//check username for duplication 
			{
			
				$rep['rep'] = false;
				$rep['msg'] = sprintf($this->lang->line('user_message_add_exist'),$post_data['username']);

				return $rep;

			}
			else
			{
			
				//We want everything O.K
				$this->db->trans_start();

				//radusergroup table
				$usergroup = array(
									'username'=>$post_data['username'],
									'groupname'=>$groupname,
									'priority'=>'1'
									);
				$this->db->insert($this->_table_usergroup,$usergroup);
				
				//radcheck table
				$radcheck = array(
								'username'=>$post_data['username'],
								'attribute'=>'Password',
								'op'=>':=',
								'value'=>encryption($post_data['password'],$val['encryption'])
								);
				$this->db->insert($this->_table_radcheck,$radcheck);

				//radcheck table
				$radcheck = array(
								'username'=>$post_data['username'],
								'attribute'=>'Auth-Type',
								'op'=>':=',
								'value'=>'Local'
								);
				$this->db->insert($this->_table_radcheck,$radcheck);

				//Expiration with format = November 28 2007 20:26:43
				$month = date('F');
				$day = date('j');
				$year = date('Y');
				$time = '24:00:00';

		if(isset($post_data['valid_until']))
		{
		
			$data['username'] = $post_data['username'];

			$date = mktime(0,0,0, date('m'), $day+$post_data['valid_until'], $year);
			$date = date("F j Y", $date)." ".$time;
			
			$data['attribute'] = 'Expiration';
			$data['op'] = ':=';
			$data['value'] = $date;
			
			//radcheck
			$this->db->insert($this->_table_radcheck,$data);
			
			//radreply
			$date_radreply = mktime(0,0,0, date('m'), $day+$post_data['valid_until'], $year);
			$date_radreply = date("Y-n-j", $date_radreply)."T".$time;

			$radreply = array(
							'username' => $data['username'],
							'attribute' => 'WISPr-Session-Terminate-Time',
							'op' => ':=',
							'value' => $date_radreply
							);
			$this->db->insert($this->_table_radreply, $radreply);
			
		}
		else
		{
			$date = mktime(0,0,0, date('m'), $day+$valid_for, $year);
				
			$date = date("F j Y", $date)." ".$time;
			$radcheck = array(
							'username'=>$post_data['username'],
							'attribute'=>'Expiration',
							'op'=>':=',
							'value'=>$date
							);
			$this->db->insert($this->_table_radcheck,$radcheck);
				
			//radreply table
			$date_radreply = mktime(0,0,0, date('m'), $day+$valid_for, $year);
			$date_radreply = date("Y-n-j", $date_radreply)."T".$time;

			$radreply = array(
							'username' => $post_data['username'],
							'attribute' => 'WISPr-Session-Terminate-Time',
							'op' => ':=',
							'value' => $date_radreply
							);
			$this->db->insert($this->_table_radreply, $radreply);
		}
					/**********************************/
					/*		กำหนดคุณสมบัตรของผู้ใช้			  */
					/**********************************/
					// กำหนดอัตราดาวน์โหลด
					$radreply_down = array(
									'username' => $post_data['username'],
									'attribute'=>'WISPr-Bandwidth-Max-Down',
									'op'=>':=',
									'value'=>$bw_download
								);
					$this->db->insert($this->_table_radreply,$radreply_down);

					// กำหนดอัตราอัพโหลด
					$radreply_up = array(
									'username' => $post_data['username'],
									'attribute'=>'WISPr-Bandwidth-Max-Up',
									'op'=>':=',
									'value'=>$bw_upload
								);
					$this->db->insert($this->_table_radreply,$radreply_up);
					
					$radreply_re = array(
									'username' => $post_data['username'],
									'attribute'=>'WISPr-Redirection-URL',
									'op'=>':=',
									'value'=>$redirect_url
								);
					$this->db->insert($this->_table_radreply,$radreply_re);	
					
					// กำหนดโปรไฟล์ชั่วโมงใช้งาน
					if($profile=='time')
					{	
						$time_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Max-All-Session',
									'op'=>':=',
									'value'=>$amount*3600
								);
						$this->db->insert($this->_table_radcheck,$time_data);
						$this->db->insert($this->_table_radreply,$time_data);

						// กำหนด วัน เริมจากการไช้งานวันแรก  วันหมดอายุ 365 ชวันนับจากเริ่มไช้งงาน 1 วัน สำหรับกลุ่มชั่วโมง ลงใน Check
						$time_data['attribute']='Expire-After';
						$time_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$time_data);
					}
						
					// กำนหดโปรไฟล์จำนวนวันใช้งาน นับจากวันเริ่มใช้
					if($profile=='timetofinish')
					{
						$timetofinish_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Expire-After',
									'op'=>':=',
									'value'=>$amount*3600*24
								);

						$this->db->insert($this->_table_radcheck,$timetofinish_data);

						$timetofinish_data['attribute']='Max-All-Session';

						$this->db->insert($this->_table_radcheck,$timetofinish_data);
						$this->db->insert($this->_table_radreply,$timetofinish_data);
						
						//if(($groupname!='bypass')&&($groupname!='teacher'))
						//if(($groupname!='20150817000433')&&($groupname!='20150817000434'))
						//{
						//$timetofinish_data = array(
						//			'username' => $post_data['username'],
						//			'attribute'=>'Session-Timeout',
						//			'op'=>':=',
						//			'value'=>'7200'
						//		);
						//$this->db->insert($this->_table_radreply,$timetofinish_data);
						//}
					}
					
					// กำหนดโปรไฟล์ชั่วโมงใช้งานต่อครั้ง
					if($profile=='timeout')
					{					
						$timeout_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Session-Timeout',
									'op'=>':=',
									'value'=>$amount*3600
								);
						$this->db->insert($this->_table_radcheck,$timeout_data);
						$this->db->insert($this->_table_radreply,$timeout_data);
						$timeout_data['attribute']='Expire-After';
						$timeout_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$timeout_data);
					}			
					
					// กำหนดโปรไฟล์ชั่วโมงใช้งานต่อวัน
					if($profile=='daily')
					{
					
						$daily_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Max-Daily-Session',
									'op'=>':=',
									'value'=>$amount*3600
								);
						$this->db->insert($this->_table_radcheck,$daily_data);
						$this->db->insert($this->_table_radreply,$daily_data);
						$daily_data['attribute']='Expire-After';
						$daily_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$daily_data);
					}		
					
					// กำหนดโปรไฟล์ชั่วโมงใช้งานต่อเดือน
					if($profile=='monthly')
					{					
						$monthly_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Max-Monthly-Session',
									'op'=>':=',
									'value'=>$amount*3600
								);
						$this->db->insert($this->_table_radcheck,$monthly_data);
						$this->db->insert($this->_table_radreply,$monthly_data);
						$monthly_data['attribute']='Expire-After';
						$monthly_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$monthly_data);
					}	
					
					// กำหนดโปรไฟล์ปริมาณข้อมูลที่ใช้
					if($profile=='packets')
					{					
						$packets_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Max-All-MB',
									'op'=>':=',
									'value'=>$amount*1024*1024
								);
						$this->db->insert($this->_table_radcheck,$packets_data);
						$packets_data['attribute']='Expire-After';
						$packets_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$packets_data);
					}			
					
					// กำหนดโปรไฟล์ปริมาณข้อมูลที่ใช้ ต่อวัน
					if($profile=='packets_day')
					{					
						$packets_day_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Mb-Per-Days',
									'op'=>':=',
									'value'=>$amount*1024*1024
								);
						$this->db->insert($this->_table_radcheck,$packets_day_data);
						$packets_day_data['attribute']='Expire-After';
						$packets_day_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$packets_day_data);
					}
					
					// กำหนดโปรไฟล์ปริมาณข้อมูลที่ใช้ ต่อเดือน
					if($profile=='packets_month')
					{					
						$packets_month_data = array(
									'username' => $post_data['username'],
									'attribute'=>'Mb-Per-Month',
									'op'=>':=',
									'value'=>$amount*1024*1024
								);
						$this->db->insert($this->_table_radcheck,$packets_month_data);
						$packets_month_data['attribute']='Expire-After';
						$packets_month_data['value'] = $valid_for*3600*24/1000;

						$this->db->insert($this->_table_radcheck,$packets_month_data);
					}
///************************************************************************************************************
			$user_data['firstname'] 	= (isset($post_data['firstname']) ? $post_data['firstname'] : '');
			$user_data['lastname'] 		= (isset($post_data['lastname']) ? $post_data['lastname'] : '');
			$user_data['surename'] 		= (isset($post_data['surename']) ? $post_data['surename'] : '');
			$user_data['gender'] 		= (isset($post_data['gender']) ? $post_data['gender'] : '');
			$user_data['web'] 			= (isset($post_data['web']) ? $post_data['web'] : '');			
			$user_data['money'] 		= (isset($post_data['money']) ? $post_data['money'] : 0);
			$user_data['ip'] 			= (isset($post_data['ip']) ? $post_data['ip'] : '');
			$user_data['mac'] 			= (isset($post_data['mac']) ? $post_data['mac'] : '');
			$user_data['personal_id'] 	= (isset($post_data['personal_id']) ? $post_data['personal_id'] : '');
			$user_data['phone'] 		= (isset($post_data['phone']) ? $post_data['phone'] : '');
			$user_data['email'] 		= (isset($post_data['email']) ? $post_data['email'] : '');
			$user_data['address1'] 		= (isset($post_data['address1']) ? $post_data['address1'] : '');
			$user_data['address2'] 		= (isset($post_data['address2']) ? $post_data['address2'] : '');
			$user_data['district'] 		= (isset($post_data['district']) ? $post_data['district'] : '');
			$user_data['amphur'] 		= (isset($post_data['amphur']) ? $post_data['amphur'] : '');
			$user_data['province'] 		= (isset($post_data['province']) ? $post_data['province'] : '');
			$user_data['note'] 			= (isset($post_data['note']) ? $post_data['note'] : '');
			//$user_data['billingplan'] 	= $billingplan->name;
			$user_data['billingplan'] 	= $name;
			$user_data['pic_upload'] 	= (isset($post_data['file_name'])) ? UPLOAD.$this->config->item('user_folder').$post_data['username'].'/'.$post_data['file_name'] : 'assets/images/noicon.jpg';
///****************************************************************************************************************			
			$profile = $this->session->_serialize($user_data);
			
				//Voucher table
				$voucher = array(
				
					'username'		=>		$post_data['username'],
					'password'		=>		$post_data['password'],
					//**********************************
					'personal_id'	=>		$post_data['personal_id'],
					'email'			=>		$post_data['email'],
					//**********************************
					'billingplan'	=>		$billingplan_name,
					'created_by'	=>		$this->session->userdata('username'),
					'valid_until'	=>		$date_radreply,
					'profile'		=>		$profile,
					'IdleTimeout'	=>		$IdleTimeout,
					'encryption'	=>		$val['encryption']
					
				);
				$this->db->insert($this->_table_voucher,$voucher);
			
				//OK stops here
				$this->db->trans_complete();
			}

		}
			
		$rep['rep'] = true;
		$rep['msg'] = sprintf($this->lang->line('user_message_add_success'),$post_data['username']);
		
		return $rep;
	

		
	
	}
}
/* End of file databasemodel.php */
/* Location: ./system/nostradius/model/databasemodel.php */