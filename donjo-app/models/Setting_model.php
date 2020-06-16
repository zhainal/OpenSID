<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Setting_model extends CI_Model {

	public function __construct()
	{
		parent::__construct();
		$pre = array();
		$CI = &get_instance();

		$this->load->helper('file');

		if ($this->setting)
		{
			return;
		}
		if ($this->config->item("useDatabaseConfig"))
		{
			// Paksa menjalankan migrasi kalau tabel setting_aplikasi
			// belum ada
			if (!$this->db->table_exists('setting_aplikasi'))
			{
				$this->load->model('database_model');
				$this->database_model->migrasi_db_cri();
			}
			$pr = $this->db
				->where("kategori is null or kategori <> 'sistem' and kategori <> 'conf_web' ")
				->order_by('key')->get("setting_aplikasi")->result();
			foreach ($pr as $p)
			{
				$pre[addslashes($p->key)] = addslashes($p->value);
			}
			$setting_sistem = $this->db
				->where('kategori', 'sistem')
				->order_by('key')->get("setting_aplikasi")->result();
			foreach ($setting_sistem as $p)
			{
				$pre[addslashes($p->key)] = addslashes($p->value);
			}
			$setting_web = $this->db
				->where('kategori', 'conf_web')
				->order_by('key')->get("setting_aplikasi")->result();
			foreach ($setting_web as $p)
			{
				$pre[addslashes($p->key)] = addslashes($p->value);
			}
		}
		else
		{
			$pre = (object) $CI->config->config;
		}
		$CI->setting = (object) $pre;
		$CI->list_setting = $pr; // Untuk tampilan daftar setting
		$CI->list_setting_web = $setting_web; // Untuk tampilan daftar setting web
		$this->apply_setting();
	}

	// Cek apakah migrasi perlu dijalankan
	private function cek_migrasi()
	{
		// Paksa menjalankan migrasi kalau belum
		// Migrasi direkam di tabel migrasi
		$sudah = false;
		if ($this->db->table_exists('migrasi') )
			$sudah = $this->db->where('versi_database', VERSI_DATABASE)
				->get('migrasi')->num_rows();
		if (!$sudah)
		{
			$this->load->model('database_model');
			$this->database_model->migrasi_db_cri();
		}
	}

	// Setting untuk PHP
	private function apply_setting()
	{
		//  https://stackoverflow.com/questions/16765158/date-it-is-not-safe-to-rely-on-the-systems-timezone-settings
		date_default_timezone_set($this->setting->timezone);//ganti ke timezone lokal
		// Ambil google api key dari desa/config/config.php kalau tidak ada di database
		if (empty($this->setting->google_key))
		{
			$this->setting->google_key = config_item('google_key');
		}
		// Ambil dev_tracker dari desa/config/config.php kalau tidak ada di database
		if (empty($this->setting->dev_tracker))
		{
			$this->setting->dev_tracker = config_item('dev_tracker');
		}
		// Kalau folder tema ubahan tidak ditemukan, ganti dengan tema default
		$pos = strpos($this->setting->web_theme, 'desa/');
		if ($pos !== false)
		{
			$folder = FCPATH . '/desa/themes/' . substr($this->setting->web_theme, $pos + strlen('desa/'));
			if (!file_exists($folder))
			{
				$this->setting->web_theme = "default";
			}
		}
		$this->setting->demo_mode = config_item('demo_mode');
		$this->cek_migrasi();
	}

	public function update($data)
	{
		$_SESSION['success'] = 1;

		foreach ($data as $key => $value)
		{
			// Update setting yang diubah
			if ($this->setting->$key != $value)
			{
				$value = strip_tags($value);
				$outp = $this->db->where('key', $key)->update('setting_aplikasi', array('key'=>$key, 'value'=>$value));
				$this->setting->$key = $value;
				if (!$outp) $_SESSION['success'] = -1;
			}
		}
		$this->apply_setting();
	}

	public function update_slider()
	{
		$_SESSION['success'] = 1;
		$this->setting->sumber_gambar_slider = $this->input->post('pilihan_sumber');
		$outp = $this->db->where('key','sumber_gambar_slider')->update('setting_aplikasi', array('value'=>$this->input->post('pilihan_sumber')));
		if (!$outp) $_SESSION['success'] = -1;
	}

	/*
		Input post:
		- jenis_server dan server_mana menentukan setting penggunaan_server
		- offline_mode dan offline_mode_saja menentukan setting offline_mode
	*/
	public function update_penggunaan_server()
	{
		$_SESSION['success'] = 1;
		$mode = $this->input->post('offline_mode_saja');
		$this->setting->offline_mode = ($mode === '0' or $mode) ? $mode : $this->input->post('offline_mode');
		$out1 = $this->db->where('key','offline_mode')->update('setting_aplikasi', array('value'=>$this->setting->offline_mode));
		$penggunaan_server = $this->input->post('server_mana') ?: $this->input->post('jenis_server');
		$this->setting->penggunaan_server = $penggunaan_server;
		$out2 = $this->db->where('key','penggunaan_server')->update('setting_aplikasi', array('value'=>$penggunaan_server));
		if (!$out1 or !$out2) $_SESSION['success'] = -1;
	}

	public function load_options()
	{
		foreach ($this->list_setting as $i => $set)
		{
			if (in_array($set->jenis, array('option', 'option-value', 'option-kode')))
			{
				$this->list_setting[$i]->options = $this->get_options($set->id);
			}
		}
	}

	private function get_options($id)
	{
		$rows = $this->db->select('id, kode, value')
		                 ->where('id_setting', $id)
		                 ->get('setting_aplikasi_options')
		                 ->result();
		return $rows;
	}

	public function get_key($id = 0)
	{
		$sql = "SELECT * FROM setting_aplikasi WHERE id = ?";
		$query = $this->db->query($sql, $id);
		$data = $query->row_array();
		return $data;
	}

	// Responsive Filemanager Key
	public function update_key($id = 0)
	{
		$pass_baru = $this->input->post('pass_baru');
		$pass_baru1 = $this->input->post('pass_baru1');

		$pwHash = $pass_baru;
		$salt = rand(100000, 999999);
		$salt = strrev($salt);
		$akey = MD5($pwHash.$salt);

		$data['value'] = $akey;

		$rfm = '<?php $config["file_manager"] ="';
		$rfm1 = $akey;
		$rfm2 = '";';
		write_file(FCPATH . LOKASI_SID_INI . 'config_rfm.php', $rfm . $rfm1 . $rfm2);

		$this->db->where('id', $id);
		$hasil = $this->db->update('setting_aplikasi', $data);
	}

	// Responsive Filemanager Key digenerate ulang setiap kali login
	public function update_key_salt($id = 0)
	{
		$id = 40;
		$pwHash = $this->setting->file_manager_key;
		$salt = rand(100000, 999999);
		$salt = strrev($salt);
		$akey = MD5($pwHash.$salt);

		$data['value'] = $akey;

		$rfm = '<?php $config["file_manager"] ="';
		$rfm1 = $akey;
		$rfm2 = '";';
		write_file(FCPATH . LOKASI_SID_INI . 'config_rfm.php', $rfm . $rfm1 . $rfm2);

		$this->db->where('id', $id);
		$hasil = $this->db->update('setting_aplikasi', $data);
	}

	// Paksa untuk update database jika belum diupdate
	public function push_update_db()
	{
		$sudah = false;
		if ($this->db->table_exists('migrasi') )
			$sudah = $this->db->where('versi_database', VERSI_DATABASE)
				->get('migrasi')->num_rows();
		if (!$sudah)
		{
			$this->load->model('database_model');
			$this->database_model->migrasi_db_cri();
		}
	}
}
