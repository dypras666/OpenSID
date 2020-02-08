<style>
  #map
  {
    width:100%;
    height:62vh
  }
  .icon {
    max-width: 70%;
    max-height: 70%;
    margin: 4px;
  }
  .leaflet-control-layers {
  	display: block;
  	position: relative;
  }
  .leaflet-control-locate a {
  font-size: 2em;
	}
</style>
<!-- Menampilkan OpenStreetMap -->
<div class="content-wrapper">
  <section class="content-header">
    <h1>Peta Wilayah <?= $nama_wilayah ?></h1>
    <ol class="breadcrumb">
      <li><a href="<?= site_url('hom_sid')?>"><i class="fa fa-home"></i> Home</a></li>
      <?php foreach ($breadcrumb as $tautan): ?>
        <li><a href="<?= $tautan['link'] ?>"> <?= $tautan['judul'] ?></a></li>
      <?php endforeach; ?>
      <li class="active">Peta Wilayah <?= $wilayah ?></li>
    </ol>
  </section>
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box box-info">
          <form action="<?= $form_action?>" method="POST" enctype="multipart/form-data" class="form-horizontal">
            <div class="box-body">
              <div class="row">
                <div class="col-sm-12">
                  <div id="map">
                    <input type="hidden" id="path" name="path" value="<?= $wil_ini['path']?>">
                    <input type="hidden" name="id" id="id"  value="<?= $wil_ini['id']?>"/>
                    <input type="hidden" name="zoom" id="zoom"  value="<?= $wil_ini['zoom']?>"/>
                </div>
              </div>
            </div>
            <div class='box-footer'>
              <div class='col-xs-12'>
                <a href="<?= $tautan['link'] ?>" class="btn btn-social btn-flat bg-purple btn-sm visible-xs-block visible-sm-inline-block visible-md-inline-block visible-lg-inline-block" title="Kembali"><i class="fa fa-arrow-circle-o-left"></i> Kembali</a>
								<a href="#" class="btn btn-social btn-flat btn-success btn-sm visible-xs-block visible-sm-inline-block visible-md-inline-block visible-lg-inline-block" download="OpenSID.gpx" id="exportGPX"><i class='fa fa-download'></i> Export ke GPX</a>
                <button type='reset' class='btn btn-social btn-flat btn-danger btn-sm' id="resetme"><i class='fa fa-times'></i> Reset</button>
                <button type='submit' class='btn btn-social btn-flat btn-info btn-sm pull-right'><i class='fa fa-check'></i> Simpan</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>

<script>
  var infoWindow;
  window.onload = function()
  {
    //Jika posisi kantor dusun belum ada, maka posisi peta akan menampilkan peta desa
    <?php if (!empty($wil_ini['lat']) && !empty($wil_ini['lng'])): ?>
      var posisi = [<?=$wil_ini['lat'].",".$wil_ini['lng']?>];
      var zoom = <?=$wil_ini['zoom']?>;
    <?php else: ?>
      var posisi = [<?=$wil_atas['lat'].",".$wil_atas['lng']?>];
      var zoom = <?=$wil_atas['zoom']?>;
    <?php endif; ?>

    //Inisialisasi tampilan peta
    var peta_wilayah = L.map('map').setView(posisi, zoom);

    //1. Menampilkan overlayLayers Peta Semua Wilayah
    var marker_desa = [];
    var marker_dusun = [];
    var marker_rw = [];
    var marker_rt = [];

    //OVERLAY WILAYAH DESA
    <?php if (!empty($desa['path'])): ?>
    var daerah_desa = <?=$desa['path']?>;
    var daftar_desa = JSON.parse('<?=addslashes(json_encode($desa))?>');
    var jml = daerah_desa[0].length;
    daerah_desa[0].push(daerah_desa[0][0]);
    for (var x = 0; x < jml; x++)
    {
      daerah_desa[0][x].reverse();
    }
    <?php if (is_file(LOKASI_LOGO_DESA . "favicon.ico")): ?>
    var point_style = stylePointLogo("<?= base_url()?><?= LOKASI_LOGO_DESA?>favicon.ico");
		<?php else: ?>
    var point_style = stylePointLogo("<?= base_url()?>favicon.ico");
		<?php endif; ?>

    marker_desa.push(turf.polygon(daerah_desa, {content: "<?=ucwords($this->setting->sebutan_desa.' '.$desa['nama_desa'])?>", style: stylePolygonDesa(), style: L.icon(point_style)}))
    marker_desa.push(turf.point([<?=$desa['lng'].",".$desa['lat']?>], {content: "Kantor Desa",style: L.icon(point_style)}));
    <?php endif; ?>

    //OVERLAY WILAYAH DUSUN
    <?php if (!empty($dusun_gis)): ?>
      set_marker(marker_dusun, '<?=addslashes(json_encode($dusun_gis))?>', '#FFFF00', '<?=ucwords($this->setting->sebutan_dusun)?>', 'dusun');
    <?php endif; ?>

    //OVERLAY WILAYAH RW
    <?php if (!empty($rw_gis)): ?>
      set_marker(marker_rw, '<?=addslashes(json_encode($rw_gis))?>', '#8888dd', 'RW', 'rw');
    <?php endif; ?>

    //OVERLAY WILAYAH RT
    <?php if (!empty($rt_gis)): ?>
      set_marker(marker_rt, '<?=addslashes(json_encode($rt_gis))?>', '#008000', 'RT', 'rt');
    <?php endif; ?>

    //Menampilkan overlayLayers Peta Semua Wilayah
    <?php if (!empty($wil_atas['path'])): ?>
      var overlayLayers = overlayWil(marker_desa, marker_dusun, marker_rw, marker_rt);
    <?php else: ?>
      var overlayLayers = {};
    <?php endif; ?>

    //Menampilkan BaseLayers Peta
    var baseLayers = getBaseLayers(peta_wilayah, '<?=$this->setting->google_key?>');

    //Menampilkan Peta wilayah yg sudah ada
    <?php if (!empty($wil_ini['path'])): ?>
      var wilayah = <?=$wil_ini['path']?>;
      showCurrentPeta(wilayah, peta_wilayah);
    <?php endif; ?>

    //Menambahkan zoom scale ke peta
    L.control.scale().addTo(peta_wilayah);

    //Menambahkan toolbar ke peta
    peta_wilayah.pm.addControls(editToolbar());

    //Menambahkan Peta wilayah
    addPetaPoly(peta_wilayah);

    // update value zoom ketika ganti zoom
    updateZoom(peta_wilayah);

    //Export/Import Peta dari file GPX/KML
    L.Control.FileLayerLoad.LABEL = '<img class="icon" src="<?= base_url()?>assets/images/folder.svg" alt="file icon"/>';
    control = eximGpx(peta_wilayah);

    //Geolocation IP Route/GPS
  	geoLocation(peta_wilayah);

    //Menghapus Peta wilayah
    hapusPeta(peta_wilayah);

    //Menampilkan baseLayers dan overlayLayers
    L.control.layers(baseLayers, overlayLayers, {position: 'topleft', collapsed: true}).addTo(peta_wilayah);

  }; //EOF window.onload
</script>
<script src="<?= base_url()?>assets/js/leaflet.filelayer.js"></script>
<script src="<?= base_url()?>assets/js/togeojson.js"></script>
