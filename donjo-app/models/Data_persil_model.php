<?php
class Data_persil_model extends CI_Model {

	public function __construct()
	{
		$this->load->database();
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
			$cari = $_SESSION['cari'];
			$kw = $this->db->escape_like_str($cari);
			$kw = '%' .$kw. '%';
			$this->db->where("p.nomor like '$kw'");
		}
	}

	public function paging($p=1)
	{
		$this->main_sql();
		$jml = $this->db->select('p.id')->get()->num_rows();

		$this->load->library('paging');
		$cfg['page'] = $p;
		$cfg['per_page'] = $_SESSION['per_page'];
		$cfg['num_rows'] = $jml;
		$this->paging->init($cfg);

		return $this->paging;
	}

	private function main_sql()
	{
		$this->db->from('persil p')
			->join('ref_persil_kelas k', 'k.id = p.kelas')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah')
			->join('mutasi_cdesa m', 'p.id = m.id_persil', 'left')
			->join('cdesa c', 'c.id = p.cdesa_awal', 'left')
			->group_by('p.nomor, nomor_urut_bidang');
		$this->search_sql();
	}

	public function list_data($offset, $per_page)
	{
		$this->main_sql();
		$data = $this->db->select('p.*, k.kode, count(m.id_persil) as jml_bidang, c.nomor as nomor_cdesa_awal')
			->select('CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) as alamat')
			->order_by('nomor, nomor_urut_bidang')
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

	public function list_persil()
	{
		$data = $this->db
			->select('p.id, nomor, nomor_urut_bidang')
			->select('CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) as lokasi')
			->from('persil p')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah')
			->order_by('nomor, nomor_urut_bidang')
			->get()->result_array();
		return $data;
	}

	public function list_c_desa($kat='', $mana=0, $offset, $per_page)
	{
		$data = [];
		$strSQL = "SELECT c.id AS id, c.nomor, m.id_cdesa_masuk, k.kode, u.nik AS nik, cu.id_pend, p.id_wilayah,  c.jenis_pemilik, u.nama as namapemilik, c.nama_pemilik_luar, c.alamat_pemilik_luar, COUNT(m.id_cdesa_masuk) AS jumlah,
			p.`lokasi`, w.rt, w.rw, w.dusun, c.created_at as tanggal_daftar,
			SUM(IF(k.kode LIke '%S%', m.luas, 0)) as basah,
			SUM(IF(k.kode LIke '%D%', m.luas, 0)) as kering
		".$this->main_sql_c_desa().$this->search_sql()."
		GROUP by c.nomor";

		$strSQL .= " LIMIT ".$offset.",".$per_page;
		$query = $this->db->query($strSQL);
		if ($query->num_rows() > 0)
		{
			$data = $query->result_array();
		}
		else
		{
			$_SESSION["pesan"]= $strSQL;
		}

		$j = $offset;
		for ($i=0; $i<count($data); $i++)
		{
			$data[$i]['no'] = $j + 1;
			if (($data[$i]['jenis_pemilik']) == 2)
			{
				$data[$i]['namapemilik'] = $data[$i]['pemilik_luar'];
				$data[$i]['nik'] = "-";
			}
			$j++;
		}
		$data = array_merge($data, $persil, $luar);
		return $data;
	}

	public function get_persil($id)
	{
		$data = $this->db->select('p.*, k.kode, k.tipe, k.ndesc, c.nomor as nomor_cdesa_awal')
			->select('CONCAT("RT ", w.rt, " / RW ", w.rw, " - ", w.dusun) as alamat')
			->from('persil p')
			->join('ref_persil_kelas k', 'k.id = p.kelas', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_wilayah', 'left')
			->join('cdesa c', 'c.id = p.cdesa_awal', 'left')
			->where('p.id', $id)
			->get()->row_array();
		return $data;
	}

	public function get_list_bidang($id)
	{
		$this->db
			->select('m.*, m.id_cdesa_masuk, c.nomor as cdesa_masuk, k.id as id_cdesa_keluar')
			->from('persil p')
			->join('mutasi_cdesa m', 'p.id = m.id_persil', 'left')
			->join('cdesa c', 'c.id = m.id_cdesa_masuk', 'left')
			->join('cdesa k', 'k.nomor = m.cdesa_keluar', 'left')
			->where('m.id_persil', $id);
		$data = $this->db->get()->result_array();
		return $data;
	}

	public function get_c_desa($id)
	{
		$data = false;
		$strSQL = "SELECT y.`id` AS id, y.`id_pend`, y.`c_desa`, u.nik AS nik, p.`jenis_pemilik`, u.`nama` as namapemilik, p.pemilik_luar, p.`alamat_luar`,w.rt, w.rw, w.dusun
		FROM data_persil_c_desa y
		LEFT JOIN data_persil p ON p.id_c_desa = y.id
		LEFT JOIN tweb_penduduk u ON u.id = y.id_pend
		LEFT JOIN tweb_wil_clusterdesa w ON w.id = u.id_cluster
		WHERE y.id =".$id ;
		$query = $this->db->query($strSQL);
		if ($query->num_rows() > 0)
		{
			$data = $query->row_array();
		}
		else
		{
			$_SESSION["pesan"]= $strSQL;
		}

		if ($data['jenis_pemilik'] == 2)
		{
			$data['namapemilik'] = $data['pemilik_luar'];
			$data['nik'] = "-";
		}
		return $data;
	}

 	private function get_persil_by_nomor($nomor, $nomor_urut_bidang)
 	{
 		$id = $this->db->select('id')
 			->where('nomor', $nomor)
 			->where('nomor_urut_bidang', $nomor_urut_bidang)
 			->get('persil')->row()->id;
 		return $id;
 	}

	public function simpan_persil($post)
	{
		$data = array();
		$data['nomor'] = bilangan($post['no_persil']);
		$data['nomor_urut_bidang'] = bilangan($post['nomor_urut_bidang']);
		$data['kelas'] = $post['kelas'];
		$data['id_wilayah'] = $post['id_wilayah'] ?: NULL;
		$data['luas_persil'] = bilangan($post['luas_persil']) ?: NULL;
		$data['lokasi'] = $post['lokasi'] ?: NULL;
		$data['cdesa_awal'] = bilangan($post['cdesa_awal']);
		$id_persil = $post['id_persil'] ?: $this->get_persil_by_nomor($post['no_persil'], $post['nomor_urut_bidang']);
		if ($id_persil)
		{
			$this->db->where('id', $id_persil)
				->update('persil', $data);
		}
		else
		{
			$data['nomor'] = $post['no_persil'];
			$this->db->insert('persil', $data);
			$id_persil = 	$this->db->insert_id();
		}
		return $id_persil;
 	}

	public function hapus($id)
	{
		$hasil = $this->db->where('id', $id)
			->delete('persil');
		status_sukses($hasil);
	}

	public function list_dusunrwrt()
	{
		$strSQL = "SELECT `id`,`rt`,`rw`,`dusun` FROM `tweb_wil_clusterdesa` WHERE (`rt`>0) ORDER BY `dusun`";
		$query = $this->db->query($strSQL);
		return $query->result_array();
	}

	public function get_penduduk($id, $nik=false)
	{
		$this->db->select('p.id, p.nik,p.nama,k.no_kk,w.rt,w.rw,w.dusun')
			->from('tweb_penduduk p')
			->join('tweb_keluarga k','k.id = p.id_kk', 'left')
			->join('tweb_wil_clusterdesa w', 'w.id = p.id_cluster', 'left');
		if ($nik)
			$this->db->where('p.nik', $id);
		else
			$this->db->where('p.id', $id);
		$data = $this->db->get()->row_array();
		return $data;
	}

	public function list_penduduk()
	{
		$strSQL = "SELECT p.nik,p.nama,k.no_kk,w.rt,w.rw,w.dusun FROM tweb_penduduk p
			LEFT JOIN tweb_keluarga k ON k.id = p.id_kk
			LEFT JOIN tweb_wil_clusterdesa w ON w.id = p.id_cluster
			WHERE 1 ORDER BY nama";
		$query = $this->db->query($strSQL);
		$data = "";
		$data = $query->result_array();
		if ($query->num_rows() > 0)
		{
			$j = 0;
			for ($i=0; $i<count($data); $i++)
			{
				if ($data[$i]['nik'] != "")
				{
					$data1[$j]['id']=$data[$i]['nik'];
					$data1[$j]['nik']=$data[$i]['nik'];
					$data1[$j]['nama']=strtoupper($data[$i]['nama'])." [NIK: ".$data[$i]['nik']."] / [NO KK: ".$data[$i]["no_kk"]."]";
					$data1[$j]['info']= "RT/RW ". $data[$i]['rt']."/".$data[$i]['rw']." - ".strtoupper($data[$i]['dusun']);
					$j++;
				}
			}
			$hasil2 = $data1;
		}
		else
		{
			$hasil2 = false;
		}
		return $hasil2;
	}

	public function list_persil_peruntukan()
	{
		$data = false;
		$strSQL = "SELECT id,nama,ndesc FROM data_persil_peruntukan WHERE 1";
		$query = $this->db->query($strSQL);
		if ($query->num_rows()>0)
		{
			$data = array();
			foreach ($query->result() as $row)
			{
				$data[$row->id] = array($row->nama,$row->ndesc);
			}
		}
		return $data;
	}

	public function get_persil_peruntukan($id=0)
	{
		$data = false;
		$strSQL = "SELECT id,nama,ndesc FROM data_persil_peruntukan WHERE id=".$id;
		$query = $this->db->query($strSQL);
		if ($query->num_rows() > 0)
		{
			$data = array();
			$data[$id] = $query->row_array();
		}
		return $data;
	}

	public function update_persil_peruntukan()
	{
		if ($this->input->post('id') == 0)
		{
			$strSQL = "INSERT INTO `data_persil_peruntukan`(`nama`,`ndesc`) VALUES('".fixSQL($this->input->post('nama'))."','".fixSQL($this->input->post('ndesc'))."')";
		}
		else
		{
			$strSQL = "UPDATE `data_persil_peruntukan` SET
			`nama` = '".fixSQL($this->input->post('nama'))."',
			`ndesc` = '".fixSQL($this->input->post('ndesc'))."'
			 WHERE id = ".$this->input->post('id');
		}

		$data["db"] = $strSQL;
		$hasil = $this->db->query($strSQL);
		if ($hasil)
		{
			$data["transaksi"] = true;
			$data["pesan"] = "Data Peruntukan Tanah ".fixSQL($this->input->post('nama'))." telah disimpan/diperbarui";
			$_SESSION["success"] = 1;
			$_SESSION["pesan"] = "Data Peruntukan Tanah ".fixSQL($this->input->post('nama'))." telah disimpan/diperbarui";
		}
		else
		{
			$data["transaksi"] = false;
			$data["pesan"] = "ERROR ".$strSQL;
		}
		return $data;
	}

	public function hapus_peruntukan($id)
	{
		$strSQL = "DELETE FROM `data_persil_peruntukan` WHERE id = ".$id;
		$hasil = $this->db->query($strSQL);
		if ($hasil)
		{
			$_SESSION["success"] = 1;
			$_SESSION["pesan"] = "Data Peruntukan Tanah telah dihapus";
		}
		else
		{
			$_SESSION["success"] = -1;
		}
	}

	public function list_persil_jenis()
	{
		$data = $this->db->order_by('nama')
			->get('data_persil_jenis')
			->result_array();
		$result = array_combine(array_column($data, 'id'), $data);
		return $result;
	}

	public function get_persil_jenis($id=0)
	{
		$data = false;
		$strSQL = "SELECT id,nama,ndesc FROM data_persil_jenis WHERE id = ".$id;
		$query = $this->db->query($strSQL);
		if ($query->num_rows() > 0)
		{
			$data = array();
			$data[$id] = $query->row_array();
		}
		return $data;
	}

	public function update_persil_jenis()
	{
		if ($this->input->post('id') == 0)
		{
			$strSQL = "INSERT INTO `data_persil_jenis`(`nama`,`ndesc`) VALUES('".strtoupper(fixSQL($this->input->post('nama')))."','".fixSQL($this->input->post('ndesc'))."')";
		}
		else
		{
			$strSQL = "UPDATE `data_persil_jenis` SET
			`nama`='".strtoupper(fixSQL($this->input->post('nama')))."',
			`ndesc`='".fixSQL($this->input->post('ndesc'))."'
			 WHERE id=".$this->input->post('id');
		}

		$data["db"] = $strSQL;
		$hasil = $this->db->query($strSQL);
		if ($hasil)
		{
			$data["transaksi"] = true;
			$data["pesan"] = "Data Jenis Tanah ".fixSQL($this->input->post('nama'))." telah disimpan/diperbarui";
			$_SESSION["success"] = 1;
			$_SESSION["pesan"] = "Data Jenis Tanah ".fixSQL($this->input->post('nama'))." telah disimpan/diperbarui";
		}
		else
		{
			$data["transaksi"] = false;
			$data["pesan"] = "ERROR ".$strSQL;
		}
		return $data;
	}

	public function hapus_jenis($id)
	{
		$strSQL = "DELETE FROM `data_persil_jenis` WHERE id = ".$id;
		$hasil = $this->db->query($strSQL);
		if ($hasil)
		{
			$_SESSION["success"] = 1;
			$_SESSION["pesan"] = "Data Jenis Tanah telah dihapus";
		}
		else
		{
			$_SESSION["success"] = -1;
		}
	}

	public function list_persil_kelas($table='')
	{
		if($table)
		{	$data =$this->db->order_by('kode')
						->get_where('ref_persil_kelas', array('tipe' => $table))
						->result_array();
			$data = array_combine(array_column($data, 'id'), $data);
		}
		else
		{
			$data = $this->db->order_by('kode')
			->get('ref_persil_kelas')
			->result_array();
			$data = array_combine(array_column($data, 'id'), $data);
		}

		return $data;
	}

	public function awal_persil($cdesa_awal, $id_persil)
	{
		$cdesa_awal = $cdesa_awal ?: null; // Kosongkan pemilik awal persil ini
		$this->db->where('id', $id_persil)
			->set('cdesa_awal', $cdesa_awal)
			->update('persil');
	}
}
?>