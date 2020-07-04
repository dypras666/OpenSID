<?php
class Cdesa_model extends CI_Model {

	public function __construct()
	{
		$this->load->database();
		$this->load->model('data_persil_model');
	}

	public function autocomplete($cari='')
	{
		$sql = "SELECT
					pemilik_luar AS nik
				FROM
					data_persil
				WHERE pemilik_luar LIKE '%$cari%'
				UNION
				SELECT
					p.nama AS nik
				FROM
					data_persil u
				LEFT JOIN tweb_penduduk p ON
					u.id_pend = p.id
				WHERE p.nama LIKE '%$cari%'";
		$query = $this->db->query($sql);
		$data = $query->result_array();

		$str = autocomplete_data_ke_str($data);
		return $str;
	}

	private function search_sql()
	{
		if (isset($_SESSION['cari']))
		{
			$cari = $this->session->cari;
			$cari = $this->db->escape_like_str($cari);
			$this->db
				->like('u.nama', $cari)
				->or_like('c.nama_pemilik_luar', $cari)
				->or_like('c.nama_kepemilikan', $cari)
				->or_like('c.nomor', $cari);
		}
	}

	private function main_sql_c_desa()
	{
		$this->db->from('cdesa c')
			->join('mutasi_cdesa m', 'm.id_cdesa_masuk = c.id or m.cdesa_keluar = c.id', 'left')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('ref_persil_kelas k', 'k.id = p.kelas', 'left')
			->join('cdesa_penduduk cu', 'cu.id_cdesa = c.id', 'left')
			->join('tweb_penduduk u', 'u.id = cu.id_pend', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = u.id_cluster', 'left');
		$this->search_sql();
	}

	public function paging_c_desa($p=1)
	{
		$this->main_sql_c_desa();
		$jml_data = $this->db
			->select('COUNT(DISTINCT c.id) AS jml')
			->get()
			->row()
			->jml;

		$this->load->library('paging');
		$cfg['page'] = $p;
		$cfg['per_page'] = $_SESSION['per_page'];
		$cfg['num_rows'] = $jml_data;
		$this->paging->init($cfg);

		return $this->paging;
	}

	public function list_c_desa($offset=0, $per_page='')
	{
		$data = [];
		$this->main_sql_c_desa();
		$this->db
			->select('c.*, c.id as id_cdesa, c.created_at as tanggal_daftar, cu.id_pend')
			->select('u.nik AS nik, u.nama as namapemilik, w.*')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN u.nama ELSE c.nama_pemilik_luar END) AS namapemilik')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) ELSE c.alamat_pemilik_luar END) AS alamat')
			->select('COUNT(m.id) AS jumlah')
			->group_by('c.id, cu.id');
		if ($per_page) $this->db->limit($per_page, $offset);
		$data = $this->db
			->get()
			->result_array();

		$j = $offset;
		for ($i=0; $i<count($data); $i++)
		{
			$data[$i]['no'] = $j + 1;
			$luas_persil = $this->jumlah_luas($data[$i]['id_cdesa']);
			$data[$i]['basah'] = $luas_persil['BASAH'];
			$data[$i]['kering'] = $luas_persil['KERING'];
			$j++;
		}
		return $data;
	}

	// Untuk cetak daftar C-Desa, menghitung jumlah luas per kelas persil
 	// Perhitungkan kasus suatu C-Desa adalah pemilik awal keseluruhan persil
	public function jumlah_luas($id_cdesa)
	{
		// luas total = jumlah luas setiap persil untuk cdesa
		// luas persil = luas keseluruhan persil (kalau pemilik awal) +/- luas setiap mutasi tergantung masuk atau keluar
		// Jumlahkan sesuai dengan tipe kelas persil (basah atau kering)
		$persil_awal = $this->db
			->select('p.id, luas_persil, k.tipe')
			->from('persil p')
			->join('ref_persil_kelas k', 'p.kelas = k.id')
			->where('cdesa_awal', $id_cdesa)
			->get()
			->result_array();
		$luas_persil = [];
		foreach ($persil_awal as $persil)
		{
			$luas_persil[$persil['tipe']][$persil['id']] = $persil['luas_persil'];
		}
		$list_mutasi = $this->db
			->select('m.id_persil, m.luas, m.cdesa_keluar, k.tipe')
			->from('mutasi_cdesa m')
			->join('persil p', 'p.id = m.id_persil')
			->join('ref_persil_kelas k', 'p.kelas = k.id')
			->where('m.id_cdesa_masuk', $id_cdesa)
			->or_where('m.cdesa_keluar', $id_cdesa)
			->get('')
			->result_array();
		foreach ($list_mutasi as $mutasi)
		{
			if ($mutasi['cdesa_keluar'] == $id_cdesa)
			{
				$luas_persil[$mutasi['tipe']][$mutasi['id_persil']] -= $mutasi['luas'];
			}
			else
			{
				$luas_persil[$mutasi['tipe']][$mutasi['id_persil']] += $mutasi['luas'];
			}
		}
		$luas_total = [];
		foreach ($luas_persil as $key => $luas)
		{
			$luas_total[$key] += array_sum($luas);
		}
		return $luas_total;
	}

	public function get_persil($id_bidang)
	{
		$data = $this->db->select('p.*, k.kode, k.tipe, k.ndesc')
			->select('CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) as alamat')
			->from('mutasi_cdesa m')
			->join('persil p', 'm.id_persil = p.id', 'left')
			->join('ref_persil_kelas k', 'k.id = p.kelas', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah', 'left')
			->where('m.id', $id_bidang)
			->get()
			->row_array();
		return $data;
	}

	public function get_bidang($id_bidang)
	{
		$data = $this->db->select('m.*')
			->from('mutasi_cdesa m')
			->where('m.id', $id_bidang)
			->get('')
			->row_array();
		return $data;
	}

	public function get_cdesa($id)
	{
		$data = $this->db->where('c.id', $id)
			->select('c.*')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN u.nama ELSE c.nama_pemilik_luar END) AS namapemilik')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) ELSE c.alamat_pemilik_luar END) AS alamat')
			->from('cdesa c')
			->join('cdesa_penduduk cu', 'cu.id_cdesa = c.id', 'left')
			->join('tweb_penduduk u', 'u.id = cu.id_pend', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = u.id_cluster', 'left')
			->limit(1)
			->get()
			->row_array();

		return $data;
	}

	public function simpan_cdesa()
	{
		$data = array();
		$data['nomor'] = bilangan_spasi($this->input->post('c_desa'));
		$data['nama_kepemilikan'] = nama($this->input->post('nama_kepemilikan'));
		$data['jenis_pemilik'] = $this->input->post('jenis_pemilik');
		$data['nama_pemilik_luar'] = nama($this->input->post('nama_pemilik_luar'));
		$data['alamat_pemilik_luar'] = strip_tags($this->input->post('alamat_pemilik_luar'));
		if ($id_cdesa = $this->input->post('id'))
		{
			$data_lama = $this->db->where('id', $id_c_desa)
				->get('cdesa')->row_array();
			if ($data['nomor'] == $data_lama['nomor']) unset($data['nomor']);
			if ($data['nama_kepemilikan'] == $data_lama['nama_kepemilikan']) unset($data['nama_kepemilikan']);
			$data['updated_by'] = $this->session->user;
			$this->db->where('id', $id_cdesa)
				->update('cdesa', $data);
		}
		else
		{
			$data['created_by'] = $this->session->user;
			$data['updated_by'] = $this->session->user;
			$this->db->insert('cdesa', $data);
			$id_cdesa = $this->db->insert_id();
		}

		if ($this->input->post('jenis_pemilik') == 1)
		{
			$this->simpan_pemilik($id_cdesa, $this->input->post('id_pend'));
		}
		else
		{
			$this->hapus_pemilik($id_cdesa);
		}
		return $id_cdesa;
	}

	private function hapus_pemilik($id_cdesa)
	{
		$this->db->where('id_cdesa', $id_cdesa)
			->delete('cdesa_penduduk');
	}

	private function simpan_pemilik($id_cdesa, $id_pend)
	{
		// Hapus pemilik lama
		$this->hapus_pemilik($id_cdesa);
		// Tambahkan pemiliki baru
		$data = array();
		$data['id_cdesa'] = $id_cdesa;
		$data['id_pend'] = $id_pend;
		$this->db->insert('cdesa_penduduk', $data);
	}

	public function simpan_mutasi($id_cdesa, $id_bidang, $post)
	{
		$data = array();
		$data['id_persil'] = $post['id_persil'];
		$data['id_cdesa_masuk'] = $id_cdesa;
		// $data['jenis_bidang_persil'] = $post['jenis_bidang_persil'];
		$data['no_bidang_persil'] = bilangan($post['no_bidang_persil']) ?: NULL;
		// $data['peruntukan'] = $post['peruntukan'];
		$data['no_objek_pajak'] = strip_tags($post['no_objek_pajak']);
		$data['no_sppt_pbb'] = strip_tags($post['no_sppt_pbb']);

		$data['tanggal_mutasi'] = $post['tanggal_mutasi'] ? tgl_indo_in($post['tanggal_mutasi']) : NULL;
		$data['jenis_mutasi'] = $post['jenis_mutasi'] ?: NULL;
		$data['luas'] = bilangan_titik($post['luas']) ?: NULL;
		$data['cdesa_keluar'] = bilangan($post['cdesa_keluar']) ?: NULL;
		$data['keterangan'] = strip_tags($post['keterangan']) ?: NULL;

		if ($id_bidang)
			$outp = $this->db->where('id', $id_bidang)->update('mutasi_cdesa', $data);
		else
			$outp = $this->db->insert('mutasi_cdesa', $data);
		if ($outp)
			{
				$_SESSION["success"] = 1;
				$_SESSION["pesan"] = "Data Persil telah DISIMPAN";
				$data["hasil"] = true;
				$data["id"]= $_POST["id_persil"];
				$data['jenis'] = $_POST["jenis"];
			}
		return $data;
	}

	public function hapus_cdesa($id)
	{
		$outp = $this->db->where('id', $id)
			->delete('cdesa');
		status_sukses($outp);
	}

	public function get_pemilik($id_cdesa)
	{
		$this->db->select('p.id, p.nik, p.nama, k.no_kk, w.rt, w.rw, w.dusun')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN p.nama ELSE c.nama_pemilik_luar END) AS namapemilik')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) ELSE c.alamat_pemilik_luar END) AS alamat')
			->from('cdesa c')
			->join('cdesa_penduduk cp', 'c.id = cp.id_cdesa', 'left')
			->join('tweb_penduduk p', 'p.id = cp.id_pend', 'left')
			->join('tweb_keluarga k','k.id = p.id_kk', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_cluster', 'left')
			->where('c.id', $id_cdesa);
		$data = $this->db->get()->row_array();

		return $data;
	}

	public function get_list_bidang($id_cdesa, $id_persil='')
	{
		$nomor_cdesa = $this->db->select('nomor')
			->where('id', $id_cdesa)
			->get('cdesa')
			->row()->nomor;
		$this->db
			->select('m.*, p.nomor, rk.kode as kelas_tanah, dp.nama as peruntukan, dj.nama as jenis_persil')
			->select('CONCAT("RT ", rt, " / RW ", rw, " - ", dusun) as lokasi, p.lokasi as alamat')
			->select("IF (m.id_cdesa_masuk = {$id_cdesa}, m.luas, '') AS luas_masuk")
			->select("IF (m.cdesa_keluar = {$id_cdesa}, m.luas, '') AS luas_keluar")
			->from('mutasi_cdesa m')
			->join('cdesa c', 'c.id = m.id_cdesa_masuk', 'left')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('data_persil_peruntukan dp', 'm.peruntukan = dp.id', 'left')
			->join('data_persil_jenis dj', 'm.jenis_bidang_persil = dj.id', 'left')
			->join('ref_persil_kelas rk', 'p.kelas = rk.id', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah', 'left')
			->group_start()
				->where('m.id_cdesa_masuk', $id_cdesa)
				->or_where('m.cdesa_keluar', $id_cdesa)
			->group_end()
			->order_by('tanggal_mutasi');
		if ($id_persil)
			$this->db->where('m.id_persil', $id_persil);
		$data = $this->db->get()->result_array();
		return $data;
	}

	public function get_list_persil($id_cdesa)
	{
		$nomor_cdesa = $this->db->select('nomor')
			->where('id', $id_cdesa)
			->get('cdesa')
			->row()->nomor;
		$this->db
			->select('p.*, rk.kode as kelas_tanah')
			->select('COUNT(m.id) as jml_mutasi')
			->select('CONCAT("RT ", rt, " / RW ", rw, " - ", dusun) as lokasi, p.lokasi as alamat')
			->from('mutasi_cdesa m')
			->join('cdesa c', 'c.id = m.id_cdesa_masuk', 'left')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('ref_persil_kelas rk', 'p.kelas = rk.id', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah', 'left')
			->group_start()
				->where('m.id_cdesa_masuk', $id_cdesa)
				->or_where('m.cdesa_keluar', $id_cdesa)
			->group_end()
			->group_by('p.id');
		$data = $this->db->get()->result_array();
		return $data;
	}

	public function list_persil_peruntukan()
	{
		$data = $this->db->order_by('nama')
			->get('data_persil_peruntukan')
			->result_array();
		$result = array_combine(array_column($data, 'id'), $data);
		return $result;
	}

	public function list_persil_jenis()
	{
		$data = $this->db->order_by('nama')
			->get('data_persil_jenis')
			->result_array();
		$result = array_combine(array_column($data, 'id'), $data);
		return $result;
	}

	public function impor_persil()
	{
		$this->load->library('Spreadsheet_Excel_Reader');
		$data = new Spreadsheet_Excel_Reader($_FILES['persil']['tmp_name']);

		$sheet = 0;
		$baris = $data->rowcount($sheet_index = $sheet);
		$kolom = $data->colcount($sheet_index = $sheet);

		for ($i=2; $i<=$baris; $i++)
		{
			$nik = $data->val($i, 2, $sheet);
			$upd['id_pend'] = $this->db->select('id')->
						where('nik', $nik)->
						get('tweb_penduduk')->row()->id;
			$upd['nama'] = $data->val($i, 3, $sheet);
			$upd['persil_jenis_id'] = $data->val($i, 4, $sheet);
			$upd['id_clusterdesa'] = $data->val($i, 5, $sheet);
			$upd['luas'] = $data->val($i, 6, $sheet);
			$upd['kelas'] = $data->val($i, 7, $sheet);
			$upd['no_sppt_pbb'] = $data->val($i, 8, $sheet);
			$upd['persil_peruntukan_id'] = $data->val($i, 9, $sheet);
			$outp = $this->db->insert('data_persil',$upd);
		}

		status_sukses($outp); //Tampilkan Pesan
	}

	public function get_cetak_bidang($id_cdesa, $tipe='')
	{
		$this->db
			->select('m.*, m.cdesa_keluar as id_cdesa_keluar, p.nomor as nopersil, p.cdesa_awal, p.luas_persil, cm.nomor as cdesa_masuk, ck.nomor as cdesa_keluar, rk.kode as kelas_tanah, rm.nama as sebabmutasi')
			->from('mutasi_cdesa m')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('ref_persil_kelas rk', 'p.kelas = rk.id', 'left')
			->join('ref_persil_mutasi rm', 'm.jenis_mutasi = rm.id', 'left')
			->join('cdesa cm', 'cm.id = m.id_cdesa_masuk', 'left')
			->join('cdesa ck', 'ck.id = m.cdesa_keluar', 'left')
			->group_start()
				->where('m.id_cdesa_masuk', $id_cdesa)
				->or_where('m.cdesa_keluar', $id_cdesa)
			->group_end()
			->where('rk.tipe', $tipe)
			->order_by('p.nomor, m.tanggal_mutasi');
		$data = $this->db->get()->result_array();
		$persil_ini = 0;
		foreach ($data as $key => $mutasi)
		{
			if ($persil_ini <> $mutasi['id_persil'] and $id_cdesa == $mutasi['cdesa_awal'])
			{
				// Cek kalau memiliki keseluruhan persil sekali saja untuk setiap persil
				// Data terurut berdasarkan persil
				$data[$key]['luas'] = $data[$key]['luas_persil'];
				$data[$key]['mutasi'] = '<p>Memiliki keseluruhan persil sejak awal</p>';
			}
			else
			{
				if ($persil_ini == $mutasi['id_persil'])
				{
					// Tidak ulangi info persil
					$data[$key]['nopersil'] = '';
					$data[$key]['kelas_tanah'] = '';
				}
				$data[$key]['mutasi'] = $this->format_mutasi($id_cdesa, $mutasi);
			}
			if ($persil_ini <> $mutasi['id_persil']) $persil_ini = $mutasi['id_persil'];
		}
		return $data;
	}

	private function format_mutasi($id_cdesa, $mutasi, $cek_cdesa_awal = false)
	{
		$keluar = $mutasi['id_cdesa_keluar'] == $id_cdesa;
		$div = $keluar ? 'class="out"' : null;
		$hasil = "<p $div>";
		$hasil .= $mutasi['sebabmutasi'];
		$hasil .= $keluar ? ' ke C No '.str_pad($mutasi['cdesa_masuk'], 4, '0', STR_PAD_LEFT) : ' dari C No '.str_pad($mutasi['cdesa_keluar'], 4, '0', STR_PAD_LEFT);
		$hasil .= !empty($mutasi['luas']) ? ", Seluas ".number_format($mutasi['luas'])." m<sup>2</sup>, " : null;
		$hasil .= !empty($mutasi['tanggal_mutasi']) ? tgl_indo_out($mutasi['tanggal_mutasi'])."<br />" : null;
		$hasil .= !empty($mutasi['keterangan']) ? $mutasi['keterangan']: null;
		$hasil .= "</p>";
		return $hasil;
	}

}
?>