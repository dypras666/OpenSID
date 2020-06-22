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
			->join('mutasi_cdesa m', 'm.id_cdesa_masuk = c.id', 'left')
			->join('mutasi_cdesa keluar', 'keluar.cdesa_keluar = c.nomor', 'left')
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
			->select('COUNT(c.id) AS jml')
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

	public function list_c_desa($offset, $per_page)
	{
		$data = [];
		$this->main_sql_c_desa();
		$this->db
			->select('c.*, c.id as id_cdesa, c.created_at as tanggal_daftar, m.id_cdesa_masuk, cu.id_pend')
			->select('u.nik AS nik, u.nama as namapemilik, w.*')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN u.nama ELSE c.nama_pemilik_luar END) AS namapemilik')
			->select('(CASE WHEN c.jenis_pemilik = 1 THEN CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) ELSE c.alamat_pemilik_luar END) AS alamat')
			->select('COUNT(m.id) + COUNT(keluar.id) AS jumlah')
			->select("SUM(CASE WHEN k.tipe = 'BASAH' THEN m.luas ELSE 0 END) as basah")
			->select("SUM(CASE WHEN k.tipe = 'KERING' THEN m.luas ELSE 0 END) as kering")
			->group_by('c.id, cu.id')
			->limit($per_page, $offset);
		$data = $this->db
			->get()
			->result_array();

		$j = $offset;
		for ($i=0; $i<count($data); $i++)
		{
			$data[$i]['no'] = $j + 1;
			$j++;
		}

		return $data;
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

	public function get_list_bidang($id_cdesa)
	{
		$nomor_cdesa = $this->db->select('nomor')
			->where('id', $id_cdesa)
			->get('cdesa')
			->row()->nomor;
		$this->db
			->select('m.*, p.nomor, rk.kode as kelas_tanah, dp.nama as peruntukan, dj.nama as jenis_persil')
			->select('CONCAT("RT ", rt, " / RW ", rw, " - ", dusun) as lokasi, p.lokasi as alamat')
			->select("IF (m.id_cdesa_masuk = {$id_cdesa} and m.cdesa_keluar IS NULL, m.luas, '') AS luas_masuk")
			->select("IF (m.cdesa_keluar IS NOT NULL, m.luas, '') AS luas_keluar")
			->from('mutasi_cdesa m')
			->join('cdesa c', 'c.id = m.id_cdesa_masuk', 'left')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('data_persil_peruntukan dp', 'm.peruntukan = dp.id', 'left')
			->join('data_persil_jenis dj', 'm.jenis_bidang_persil = dj.id', 'left')
			->join('ref_persil_kelas rk', 'p.kelas = rk.id', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah', 'left')
			->where('m.id_cdesa_masuk', $id_cdesa)
			->or_where('m.cdesa_keluar', $nomor_cdesa)
			->order_by('tanggal_mutasi');
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
			->where('m.id_cdesa_masuk', $id_cdesa)
			->or_where('m.cdesa_keluar', $nomor_cdesa)
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
			->select('m.*, p.nomor as nopersil, rk.kode as kelas_tanah')
			->from('mutasi_cdesa m')
			->join('persil p', 'p.id = m.id_persil', 'left')
			->join('ref_persil_kelas rk', 'p.kelas = rk.id', 'left')
			->where('m.id_cdesa_masuk', $id_cdesa)
			->where('rk.tipe', $tipe);
		$data = $this->db->get()->result_array();
		foreach ($data as $key => $item)
		{
			$data[$key]['mutasi'] = $this->format_mutasi($item);
		}
		return $data;
	}

	private function format_mutasi($mutasi)
	{
		if($mutasi)
		{
			$div = ($mutasi['jenis_mutasi'] == 2)? 'class="out"':null;
			$hasil = "<p $div>";
			$hasil .= $mutasi['sebabmutasi'];
			$hasil .= !empty($mutasi['no_c_desa']) ? " ".ket_mutasi_persil($mutasi['jenis_mutasi'])." C No ".sprintf("%04s",$mutasi['no_c_desa']): null;
			$hasil .= !empty($mutasi['luas']) ? ", Seluas ".number_format($mutasi['luas'])." m<sup>2</sup>, " : null;
			$hasil .= !empty($mutasi['tanggal_mutasi']) ? tgl_indo_out($mutasi['tanggal_mutasi'])."<br />" : null;
			$hasil .= !empty($mutasi['keterangan']) ? $mutasi['keterangan']: null;
			$hasil .= "</p>";

			return $hasil;

		}
	}

}
?>