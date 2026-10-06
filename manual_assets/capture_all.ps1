$edge = "C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
$profile = "e:\laragon\www\sirevita\manual_assets\edge_profile"

$pages = @(
    @{ name = "03_sekolah.png"; url = "http://sirevita.test/schools" },
    @{ name = "04_proyek.png"; url = "http://sirevita.test/projects" },
    @{ name = "05_rab.png"; url = "http://sirevita.test/rab" },
    @{ name = "06_transaksi.png"; url = "http://sirevita.test/transactions" },
    @{ name = "07_tambah_transaksi.png"; url = "http://sirevita.test/transactions/create" },
    @{ name = "08_bku.png"; url = "http://sirevita.test/laporan/bku" },
    @{ name = "09_bpk.png"; url = "http://sirevita.test/laporan/bpk" },
    @{ name = "10_bb.png"; url = "http://sirevita.test/laporan/bb" },
    @{ name = "11_bp.png"; url = "http://sirevita.test/laporan/bp" },
    @{ name = "12_realisasi.png"; url = "http://sirevita.test/laporan/realisasi" },
    @{ name = "13_kuitansi_list.png"; url = "http://sirevita.test/laporan/kuitansi" },
    @{ name = "14_cetak_kuitansi.png"; url = "http://sirevita.test/laporan/kuitansi/3/print" },
    @{ name = "15_roles.png"; url = "http://sirevita.test/roles" }
)

foreach ($p in $pages) {
    $out = "e:\laragon\www\sirevita\manual_assets\" + $p.name
    Write-Host "Capturing $($p.name)..."
    Start-Process -FilePath $edge -ArgumentList "--headless=new", "--disable-gpu", "--user-data-dir=$profile", "--window-size=1280,850", "--screenshot=$out", $p.url -Wait
    Start-Sleep -Milliseconds 800
}

Write-Host "All screenshots captured successfully!"

