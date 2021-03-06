<?php

$app->get('/acc/l_arus_kas_custom/getSetting', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

    $models = $db->select("acc_m_setting_arus_kas.*, acc_m_akun.kode as kodeAkun, acc_m_akun.nama as namaAkun")
                    ->from('acc_m_setting_arus_kas')
                    ->join("LEFT JOIN", "acc_m_akun", "acc_m_akun.id = acc_m_setting_arus_kas.m_akun_id")
                    ->orderBy("id")->findAll();

    $arr = [];
    foreach ($models as $key => $value) {
        if (!empty($value->m_akun_id)) {
            $akun = implode(", ", json_decode($value->m_akun_id));
            $value->akun = !empty($value->m_akun_id) ? $db->select("id, kode, nama")->from("acc_m_akun")->customWhere("id IN($akun)", "AND")->findAll() : [];
        }

        $arr[$value->tipe]['tipe'] = $value->tipe;
        $arr[$value->tipe]['tipe'] = $value->tipe;
        $arr[$value->tipe]['detail'][] = (array) $value;
    }

    return successResponse($response, ['data' => $arr, 'status' => !empty($arr) ? true : false]);
});

$app->post('/acc/l_arus_kas_custom/saveSetting', function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;

//    print_r($params);
//    die;

    try {
        $delete = true;
        $id = 1;
        foreach ($params['form'] as $key => $value) {
            if (isset($value['detail']) && !empty($value['detail'])) {
                if ($delete) {
                    $db->run("DELETE FROM acc_m_setting_arus_kas");
                    $delete = false;
                }
                foreach ($value['detail'] as $k => $v) {
                    $data = [];
                    $data['id'] = $id;
                    $data['tipe'] = $value['tipe'];
                    $data['nama'] = $v['nama'];
                    if (isset($v['akun'][0]) && !empty($v['akun'][0])) {
                        $akunId = [];
                        foreach ($v['akun'] as $a => $b) {
                            $akunId[] = $b['id'];
                        }
                        $data['m_akun_id'] = json_encode($akunId);
                    }
                    $data['is_total'] = isset($v['is_total']) ? $v['is_total'] : 0;
                    $db->insert("acc_m_setting_arus_kas", $data);
                    $id++;
                }
            }
        }
        return successResponse($response, []);
    } catch (Exception $exc) {
        return unprocessResponse($response, ["terjadi masalah pada server"]);
    }
});

$app->get('/acc/l_arus_kas_custom/laporan', function ($request, $response) {

    $subDomain = str_replace('api/', '', site_url());

//    pd($subDomain);
    $data['img'] = imgLaporan();

    $params = $request->getParams();
    $db = $this->db;

//    print_die($params);
    //tanggal awal
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    //tanggal akhir
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");

    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' s/d ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

    if (isset($params['m_lokasi_id'])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if (!empty($lokasiId)) {
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */ else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }

    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

//    $tipe_arus = ["Aktivitas Operasi", "Investasi", "Pendanaan", "Tidak Terklasifikasi"];

    $data_penerimaan = $params;
    $data_penerimaan['tipe'] = 'penerimaan';
    $data_pengeluaran = $params;
    $data_pengeluaran['tipe'] = 'pengeluaran';
    $penerimaan = jurnalKas($data_penerimaan);
    $pengeluaran = jurnalKas($data_pengeluaran);

//    pd($penerimaan);
//    pd($pengeluaran);
//    $akun_merge = array_merge($penerimaan['data']['total_akun']['kredit'], $pengeluaran['data']['total_akun']['debit']);
    $akun_merge_fix = [];

    $data['total_penerimaan'] = 0;
    if (!empty($penerimaan['data']['total_akun'])) {
        foreach ($penerimaan['data']['total_akun']['kredit'] as $key => $value) {
//        if (isset($akun_merge_fix[$value['akun']['kode']])) {
//            $akun_merge_fix[$value['akun']['kode']]['total'] += $value['total'];
//        } else {
//            $akun_merge_fix[$value['akun']['kode']] = $value;
//        }

            $akun_merge_fix['PENERIMAAN'][$value['akun']['kode']] = $value;
            $data['total_penerimaan'] += $value['total'];
        }
    }


//    pd($akun_merge_fix);

    $data['total_pengeluaran'] = 0;
    if (!empty($pengeluaran['data']['total_akun'])) {
        foreach ($pengeluaran['data']['total_akun']['debit'] as $key => $value) {
//        if (isset($akun_merge_fix[$value['akun']['kode']])) {
//            $akun_merge_fix[$value['akun']['kode']]['total'] -= $value['total'];
//        } else {
//            $akun_merge_fix[$value['akun']['kode']] = $value;
//            $akun_merge_fix[$value['akun']['kode']]['total'] = ($value['total'] * -1);
//        }

            $akun_merge_fix['PENGELUARAN'][$value['akun']['kode']] = $value;
            $akun_merge_fix['PENGELUARAN'][$value['akun']['kode']]['total'] = ($value['total'] * -1);
            $data['total_pengeluaran'] += $value['total'];
        }
    }


//    pd($akun_merge_fix);
//    pd($akun_merge);

    $arr = [];
    $data['saldo_biaya'] = 0;
    foreach ($akun_merge_fix as $k => $v) {
        foreach ($v as $key => $value) {
            $value['akun']['tipe_arus'] = !empty($value['akun']['tipe_arus']) ? $value['akun']['tipe_arus'] : 'Tidak Terklasifikasi';
//            $tipe = $value['total'] < 0 ? 'PENGELUARAN' : 'PENERIMAAN';
            $tipe = $k;
//        $value['total'] = $value['akun']['saldo_normal'] == 1 ? ($value['total'] * -1) : $value['total'];

            if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']])) {
                $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'] += $value['total'];
            } else {
                $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'] = $value['total'];
                $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['akun'] = $value['akun'];
            }

            if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'])) {
                $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'] += $value['total'];
            } else {
                $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'] = $value['total'];
            }

            if (isset($arr[$value['akun']['tipe_arus']]['total'])) {
                $arr[$value['akun']['tipe_arus']]['total'] += $value['total'];
            } else {
                $arr[$value['akun']['tipe_arus']]['total'] = $value['total'];
            }

            if ($value['akun']['tipe_arus'] != 'Tidak Terklasifikasi') {
                $data['saldo_biaya'] += $value['total'];
            }
        }
    }

//    print_die($arr);

    $akun_merge_kas = [];
    $index = 0;
    if (!empty($penerimaan['data']['total_akun'])) {
        foreach ($penerimaan['data']['total_akun']['debit'] as $key => $value) {
            $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
            $akun_merge_kas[$index]['kodeAkun'] = $value['akun']['kode'];
            $akun_merge_kas[$index]['debit'] = $value['total'];
            $akun_merge_kas[$index]['kredit'] = 0;
            $index++;
        }
    }

    if (!empty($pengeluaran['data']['total_akun'])) {
        foreach ($pengeluaran['data']['total_akun']['kredit'] as $key => $value) {
            $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
            $akun_merge_kas[$index]['kodeAkun'] = $value['akun']['kode'];
            $akun_merge_kas[$index]['debit'] = 0;
            $akun_merge_kas[$index]['kredit'] = $value['total'];
            $index++;
        }
    }


//    pd($akun_merge_kas);

    /*
     * saldo awal & akhir
     */
    $arrAkun = [];
    $arrAwal = [];
    $arrPeriode = [];
    $arrAkhir = [];
    $akun_kas = $db->select("*")->from("acc_m_akun")->where("is_kas", "=", 1)->where("is_tipe", "=", 0)->findAll();
    if (!empty($akun_kas)) {
        foreach ($akun_kas as $key => $value) {
            $arrAwal['detail'][$value->kode] = (array) $value;
            $arrPeriode['detail'][$value->kode] = (array) $value;
            $arrAkhir['detail'][$value->kode] = (array) $value;
            $arrAkun[] = $value->id;
        }

//    pd($arrAwal);


        $arrAkun = implode(", ", $arrAkun);

        $db->select("SUM(debit) as debit, SUM(kredit) as kredit, m_akun_id, acc_m_akun.kode as kodeAkun")->from("acc_trans_detail")
                ->join("left join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                ->customWhere("m_akun_id IN($arrAkun)", "AND")
                ->where("date(tanggal)", "<", $tanggal_start);

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
        }
        $saldo_awal = $db->groupBy("m_akun_id")->findAll();

//    pd($saldo_awal);

        $db->select("SUM(debit) as debit, SUM(kredit) as kredit, m_akun_id, acc_m_akun.kode as kodeAkun")->from("acc_trans_detail")
                ->join("left join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                ->customWhere("m_akun_id IN($arrAkun)", "AND")
                ->where("date(tanggal)", "<=", $tanggal_end);

        if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
            $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
        }

        $saldo_akhir = $db->groupBy("m_akun_id")->findAll();

//    pd($saldo_awal);

        foreach ($saldo_awal as $key => $value) {
            if (isset($arrAwal['detail'][$value->kodeAkun]['debit'])) {
                $arrAwal['detail'][$value->kodeAkun]['debit'] += $value->debit;
                $arrAwal['detail'][$value->kodeAkun]['kredit'] += $value->kredit;
                $arrAwal['detail'][$value->kodeAkun]['total'] += $value->debit - $value->kredit;
            } else {
                $arrAwal['detail'][$value->kodeAkun]['debit'] = $value->debit;
                $arrAwal['detail'][$value->kodeAkun]['kredit'] = $value->kredit;
                $arrAwal['detail'][$value->kodeAkun]['total'] = $value->debit - $value->kredit;
            }

            if (isset($arrAwal['total'])) {
                $arrAwal['total'] += $value->debit - $value->kredit;
            } else {
                $arrAwal['total'] = !empty($value->debit) ? $value->debit - $value->kredit : 0;
            }
        }

//    pd($akun_merge_kas);

        foreach ($akun_merge_kas as $key => $value) {
            if (isset($arrPeriode['detail'][$value['kodeAkun']]['debit'])) {
                $arrPeriode['detail'][$value['kodeAkun']]['debit'] += $value['debit'];
                $arrPeriode['detail'][$value['kodeAkun']]['kredit'] += $value['kredit'];
                $arrPeriode['detail'][$value['kodeAkun']]['total'] += $value['debit'] - $value['kredit'];
            } else {
                $arrPeriode['detail'][$value['kodeAkun']]['debit'] = $value['debit'];
                $arrPeriode['detail'][$value['kodeAkun']]['kredit'] = $value['kredit'];
                $arrPeriode['detail'][$value['kodeAkun']]['total'] = $value['debit'] - $value['kredit'];
            }

            if (isset($arrPeriode['total'])) {
                $arrPeriode['total'] += $value['debit'] - $value['kredit'];
            } else {
                $arrPeriode['total'] = !empty($value['debit']) ? $value['debit'] - $value['kredit'] : 0;
            }
        }

        foreach ($saldo_akhir as $key => $value) {
            if (isset($arrAkhir['detail'][$value->kodeAkun]['debit'])) {
                $arrAkhir['detail'][$value->kodeAkun]['debit'] += $value->debit;
                $arrAkhir['detail'][$value->kodeAkun]['kredit'] += $value->kredit;
                $arrAkhir['detail'][$value->kodeAkun]['total'] += $value->debit - $value->kredit;
            } else {
                $arrAkhir['detail'][$value->kodeAkun]['debit'] = $value->debit;
                $arrAkhir['detail'][$value->kodeAkun]['kredit'] = $value->kredit;
                $arrAkhir['detail'][$value->kodeAkun]['total'] = $value->debit - $value->kredit;
            }

            if (isset($arrAkhir['total'])) {
                $arrAkhir['total'] += $value->debit - $value->kredit;
            } else {
                $arrAkhir['total'] = !empty($value->debit) ? $value->debit - $value->kredit : 0;
            }
        }
    }


    $data['saldo_awal'] = $arrAwal;
    $data['saldo_periode'] = $arrPeriode;
    $data['saldo_akhir'] = $arrAkhir;

//    pd($akun_kas);
//    pd($arr);

    if (!empty($akun_kas)) {
        ksort($arr);
        ksort($arrAwal['detail']);
        ksort($arrAkhir['detail']);
        ksort($arrPeriode['detail']);
    }

    foreach ($arr as $key => $value) {
        if ($key == 'Aktivitas Operasi') {
            $arr[$key]['nama'] = 'AKTIVITAS OPERASI';
        } else if ($key == 'Investasi') {
            $arr[$key]['nama'] = 'AKTIVITAS INVESTASI';
        } else if ($key == 'Pendanaan') {
            $arr[$key]['nama'] = 'AKTIVITAS PENDANAAN';
        }
        ksort($arr[$key]['detail']);
        foreach ($value['detail'] as $keys => $values) {
//            foreach ($values['detail'] as $k => $v) {
            ksort($arr[$key]['detail'][$keys]['detail']);
//            }
        }
    }

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustom.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-arus-kas.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustom.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 5000);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
})->setName('lArusKas');

$app->get('/acc/l_arus_kas_custom/laporan_periode', function ($request, $response) {

    $subDomain = str_replace('api/', '', site_url());

//    pd($subDomain);
    $data['img'] = imgLaporan();

    $params = $request->getParams();
    $db = $this->db;

//    print_die($params);
    //tanggal awal
    $tanggal_awal = new DateTime($params['startDate']);
    $tanggal_awal->setTimezone(new DateTimeZone('Asia/Jakarta'));
    //tanggal akhir
    $tanggal_akhir = new DateTime($params['endDate']);
    $tanggal_akhir->setTimezone(new DateTimeZone('Asia/Jakarta'));

    $tanggal_start = $tanggal_awal->format("Y-m-d");
    $tanggal_end = $tanggal_akhir->format("Y-m-d");

    $data['tanggal'] = date("d-m-Y", strtotime($tanggal_start)) . ' s/d ' . date("d-m-Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

    if (isset($params['m_lokasi_id'])) {
        $lokasiId = getChildId("acc_m_lokasi", $params['m_lokasi_id']);
        /*
         * jika lokasi punya child
         */
        if (!empty($lokasiId)) {
            $lokasiId[] = $params['m_lokasi_id'];
            $lokasiId = implode(",", $lokasiId);
        }
        /*
         * jika lokasi tidak punya child
         */ else {
            $lokasiId = $params['m_lokasi_id'];
        }
    }

    $data['tanggal'] = date("d M Y", strtotime($tanggal_start)) . ' s/d ' . date("d M Y", strtotime($tanggal_end));
    $data['disiapkan'] = date("d-m-Y, H:i");
    $data['lokasi'] = $params['nama_lokasi'];

//    $tipe_arus = ["Aktivitas Operasi", "Investasi", "Pendanaan", "Tidak Terklasifikasi"];

    /*
     * generate tanggal per bulan
     */
    $tanggal_sekarang = $tanggal_start;
    $tanggal_akhir_bulan = date('Y-m-t', strtotime($tanggal_sekarang));
    $arr_tanggal = [];
    $add = true;
    while ($tanggal_akhir_bulan <= $tanggal_end) {
        $arr_tanggal[] = [
            'awal' => $tanggal_sekarang,
            'akhir' => $tanggal_akhir_bulan,
            'nama' => date('F', strtotime($tanggal_sekarang)),
            'number' => date('n', strtotime($tanggal_sekarang)),
        ];
        $tanggal_sekarang = date('Y-m-d', strtotime($tanggal_akhir_bulan . '+1 days'));
        $tanggal_akhir_bulan = date('Y-m-t', strtotime($tanggal_sekarang));
        if ($tanggal_sekarang > $tanggal_end) {
            $add = false;
        }

        if ($tanggal_akhir_bulan == $tanggal_end) {
            $add = false;
        }
    }

    /*
     * jika tanggal akhir bukan 30/31
     */
    if ($add) {
        $arr_tanggal[] = [
            'awal' => $tanggal_sekarang,
            'akhir' => $tanggal_end,
            'nama' => date('F', strtotime($tanggal_sekarang)),
            'number' => date('n', strtotime($tanggal_sekarang)),
        ];
    }

//    print_die($arr_tanggal);

    $arr_hasil = [];
    $arr = [];
    $arrAkun = [];
    $arrAwal = [];
    $arrPeriode = [];
    $arrAkhir = [];
    $data['saldo_biaya'] = [];

    /*
     * saldo awal & akhir
     */
    $akun_kas = $db->select("*")->from("acc_m_akun")->where("is_kas", "=", 1)->where("is_tipe", "=", 0)->findAll();

    if (!empty($akun_kas)) {
        foreach ($akun_kas as $key => $value) {
            $arrAwal['detail'][$value->kode] = (array) $value;
            $arrPeriode['detail'][$value->kode] = (array) $value;
            $arrAkhir['detail'][$value->kode] = (array) $value;
            $arrAkun[] = $value->id;
        }
    }


    $arrAkun = implode(", ", $arrAkun);

    foreach ($arr_tanggal as $a => $b) {

        $params['startDate'] = $b['awal'];
        $params['endDate'] = $b['akhir'];

        $tanggal_start = $b['awal'];
        $tanggal_end = $b['akhir'];

        $data_penerimaan = $params;
        $data_penerimaan['tipe'] = 'penerimaan';
        $data_pengeluaran = $params;
        $data_pengeluaran['tipe'] = 'pengeluaran';
        $penerimaan = jurnalKas($data_penerimaan);
        $pengeluaran = jurnalKas($data_pengeluaran);

//    pd($penerimaan);
//    pd($pengeluaran);

        $akun_merge_fix = [];

        $data['total_penerimaan'] = 0;
        if (!empty($penerimaan['data']['total_akun'])) {
            foreach ($penerimaan['data']['total_akun']['kredit'] as $key => $value) {
                $akun_merge_fix['PENERIMAAN'][$value['akun']['kode']] = $value;
                $akun_merge_fix['PENERIMAAN'][$value['akun']['kode']]['total2'][$b['number']] = $value['total'];
                $data['total_penerimaan'] += $value['total'];
            }
        }

//    pd($akun_merge_fix);

        $data['total_pengeluaran'] = 0;
        if (!empty($pengeluaran['data']['total_akun'])) {
            foreach ($pengeluaran['data']['total_akun']['debit'] as $key => $value) {
                $akun_merge_fix['PENGELUARAN'][$value['akun']['kode']] = $value;
                $akun_merge_fix['PENGELUARAN'][$value['akun']['kode']]['total'] = ($value['total'] * -1);
                $akun_merge_fix['PENGELUARAN'][$value['akun']['kode']]['total2'][$b['number']] = ($value['total'] * -1);
                $data['total_pengeluaran'] += $value['total'];
            }
        }

//    print_die($akun_merge_fix);


        foreach ($akun_merge_fix as $k => $v) {
            foreach ($v as $key => $value) {
                $value['akun']['tipe_arus'] = !empty($value['akun']['tipe_arus']) ? $value['akun']['tipe_arus'] : 'Tidak Terklasifikasi';
                $tipe = $k;

                if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']])) {
                    if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'][$b['number']])) {
                        $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'][$b['number']] += $value['total'];
                    } else {
                        $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'][$b['number']] = $value['total'];
                    }
                } else {
                    $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['total'][$b['number']] = $value['total'];
                    $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['detail'][$value['akun']['kode']]['akun'] = $value['akun'];
                }

                if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'])) {
                    if (isset($arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'][$b['number']])) {
                        $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'][$b['number']] += $value['total'];
                    } else {
                        $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'][$b['number']] = $value['total'];
                    }
                } else {
                    $arr[$value['akun']['tipe_arus']]['detail'][$tipe]['total'][$b['number']] = $value['total'];
                }

                if (isset($arr[$value['akun']['tipe_arus']]['total'])) {
                    if (isset($arr[$value['akun']['tipe_arus']]['total'][$b['number']])) {
                        $arr[$value['akun']['tipe_arus']]['total'][$b['number']] += $value['total'];
                    } else {
                        $arr[$value['akun']['tipe_arus']]['total'][$b['number']] = $value['total'];
                    }
                } else {
                    $arr[$value['akun']['tipe_arus']]['total'][$b['number']] = $value['total'];
                }

                if ($value['akun']['tipe_arus'] != 'Tidak Terklasifikasi') {
                    if (isset($data['saldo_biaya'][$b['number']])) {
                        $data['saldo_biaya'][$b['number']] += $value['total'];
                    } else {
                        $data['saldo_biaya'][$b['number']] = $value['total'];
                    }
                }
            }
        }

//        print_die($arr);

        $akun_merge_kas = [];
        $index = 0;
        if (!empty($penerimaan['data']['total_akun'])) {
            foreach ($penerimaan['data']['total_akun']['debit'] as $key => $value) {
                $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
                $akun_merge_kas[$index]['kodeAkun'] = $value['akun']['kode'];
                $akun_merge_kas[$index]['debit'] = $value['total'];
                $akun_merge_kas[$index]['kredit'] = 0;
                $akun_merge_kas[$index]['debit2'][$b['number']] = $value['total'];
                $akun_merge_kas[$index]['kredit2'][$b['number']] = 0;
                $index++;
            }
        }

        if (!empty($pengeluaran['data']['total_akun'])) {
            foreach ($pengeluaran['data']['total_akun']['kredit'] as $key => $value) {
                $akun_merge_kas[$index]['m_akun_id'] = $value['akun']['id'];
                $akun_merge_kas[$index]['kodeAkun'] = $value['akun']['kode'];
                $akun_merge_kas[$index]['debit'] = 0;
                $akun_merge_kas[$index]['kredit'] = $value['total'];
                $akun_merge_kas[$index]['debit2'][$b['number']] = 0;
                $akun_merge_kas[$index]['kredit2'][$b['number']] = $value['total'];
                $index++;
            }
        }

//
//        print_die($akun_merge_kas);
        if (!empty($akun_kas)) {
            $db->select("SUM(debit) as debit, SUM(kredit) as kredit, m_akun_id, acc_m_akun.kode as kodeAkun")->from("acc_trans_detail")
                    ->join("left join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                    ->customWhere("m_akun_id IN($arrAkun)", "AND")
                    ->where("date(tanggal)", "<", $tanggal_start);

            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
            }
            $saldo_awal = $db->groupBy("m_akun_id")->findAll();
//
//        print_die($saldo_awal);
//
            $db->select("SUM(debit) as debit, SUM(kredit) as kredit, m_akun_id, acc_m_akun.kode as kodeAkun")->from("acc_trans_detail")
                    ->join("left join", "acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
                    ->customWhere("m_akun_id IN($arrAkun)", "AND")
                    ->where("date(tanggal)", "<=", $tanggal_end);

            if (isset($params['m_lokasi_id']) && !empty($params['m_lokasi_id'])) {
                $db->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
            }

            $saldo_akhir = $db->groupBy("m_akun_id")->findAll();

//        print_die($saldo_akhir);
//
            foreach ($saldo_awal as $key => $value) {
                if (isset($arrAwal['detail'][$value->kodeAkun]['debit'])) {
                    if (isset($arrAwal['detail'][$value->kodeAkun]['debit'][$b['number']])) {
                        $arrAwal['detail'][$value->kodeAkun]['debit'][$b['number']] += $value->debit;
                    } else {
                        $arrAwal['detail'][$value->kodeAkun]['debit'][$b['number']] = $value->debit;
                    }

                    if (isset($arrAwal['detail'][$value->kodeAkun]['kredit'][$b['number']])) {
                        $arrAwal['detail'][$value->kodeAkun]['kredit'][$b['number']] += $value->kredit;
                    } else {
                        $arrAwal['detail'][$value->kodeAkun]['kredit'][$b['number']] = $value->kredit;
                    }

                    if (isset($arrAwal['detail'][$value->kodeAkun]['total'][$b['number']])) {
                        $arrAwal['detail'][$value->kodeAkun]['total'][$b['number']] += $value->debit - $value->kredit;
                    } else {
                        $arrAwal['detail'][$value->kodeAkun]['total'][$b['number']] = $value->debit - $value->kredit;
                    }
                    $arrAwal['detail'][$value->kodeAkun]['sum_total'] += $value->debit - $value->kredit;
                } else {
                    $arrAwal['detail'][$value->kodeAkun]['debit'][$b['number']] = $value->debit;
                    $arrAwal['detail'][$value->kodeAkun]['kredit'][$b['number']] = $value->kredit;
                    $arrAwal['detail'][$value->kodeAkun]['total'][$b['number']] = $value->debit - $value->kredit;
                    $arrAwal['detail'][$value->kodeAkun]['sum_total'] = $value->debit - $value->kredit;
                }

                if (isset($arrAwal['total'])) {
                    if (isset($arrAwal['total'][$b['number']])) {
                        $arrAwal['total'][$b['number']] += $value->debit - $value->kredit;
                    } else {
                        $arrAwal['total'][$b['number']] = $value->debit - $value->kredit;
                    }
                } else {
                    $arrAwal['total'][$b['number']] = !empty($value->debit) ? $value->debit - $value->kredit : 0;
                }
            }
//
//        print_die($arrAwal);
//
            foreach ($akun_merge_kas as $key => $value) {
                if (isset($arrPeriode['detail'][$value['kodeAkun']]['debit'])) {
                    if (isset($arrPeriode['detail'][$value['kodeAkun']]['debit'][$b['number']])) {
                        $arrPeriode['detail'][$value['kodeAkun']]['debit'][$b['number']] += $value['debit'];
                    } else {
                        $arrPeriode['detail'][$value['kodeAkun']]['debit'][$b['number']] = $value['debit'];
                    }

                    if (isset($arrPeriode['detail'][$value['kodeAkun']]['kredit'][$b['number']])) {
                        $arrPeriode['detail'][$value['kodeAkun']]['kredit'][$b['number']] += $value['kredit'];
                    } else {
                        $arrPeriode['detail'][$value['kodeAkun']]['kredit'][$b['number']] = $value['kredit'];
                    }

                    if (isset($arrPeriode['detail'][$value['kodeAkun']]['total'][$b['number']])) {
                        $arrPeriode['detail'][$value['kodeAkun']]['total'][$b['number']] += $value['debit'] - $value['kredit'];
                    } else {
                        $arrPeriode['detail'][$value['kodeAkun']]['total'][$b['number']] = $value['debit'] - $value['kredit'];
                    }
                } else {
                    $arrPeriode['detail'][$value['kodeAkun']]['debit'][$b['number']] = $value['debit'];
                    $arrPeriode['detail'][$value['kodeAkun']]['kredit'][$b['number']] = $value['kredit'];
                    $arrPeriode['detail'][$value['kodeAkun']]['total'][$b['number']] = $value['debit'] - $value['kredit'];
                }

                if (isset($arrPeriode['total'])) {
                    if (isset($arrPeriode['total'][$b['number']])) {
                        $arrPeriode['total'][$b['number']] += $value['debit'] - $value['kredit'];
                    } else {
                        $arrPeriode['total'][$b['number']] = $value['debit'] - $value['kredit'];
                    }
                } else {
                    $arrPeriode['total'][$b['number']] = !empty($value['debit']) ? $value['debit'] - $value['kredit'] : 0;
                }
            }

            foreach ($saldo_akhir as $key => $value) {
                if (isset($arrAkhir['detail'][$value->kodeAkun]['debit'])) {
                    if (isset($arrAkhir['detail'][$value->kodeAkun]['debit'][$b['number']])) {
                        $arrAkhir['detail'][$value->kodeAkun]['debit'][$b['number']] += $value->debit;
                    } else {
                        $arrAkhir['detail'][$value->kodeAkun]['debit'][$b['number']] = $value->debit;
                    }

                    if (isset($arrAkhir['detail'][$value->kodeAkun]['kredit'][$b['number']])) {
                        $arrAkhir['detail'][$value->kodeAkun]['kredit'][$b['number']] += $value->kredit;
                    } else {
                        $arrAkhir['detail'][$value->kodeAkun]['kredit'][$b['number']] = $value->kredit;
                    }

                    if (isset($arrAkhir['detail'][$value->kodeAkun]['total'][$b['number']])) {
                        $arrAkhir['detail'][$value->kodeAkun]['total'][$b['number']] += $value->debit - $value->kredit;
                    } else {
                        $arrAkhir['detail'][$value->kodeAkun]['total'][$b['number']] = $value->debit - $value->kredit;
                    }
                    $arrAkhir['detail'][$value->kodeAkun]['sum_total'] += $value->debit - $value->kredit;
                } else {
                    $arrAkhir['detail'][$value->kodeAkun]['debit'][$b['number']] = $value->debit;
                    $arrAkhir['detail'][$value->kodeAkun]['kredit'][$b['number']] = $value->kredit;
                    $arrAkhir['detail'][$value->kodeAkun]['total'][$b['number']] = $value->debit - $value->kredit;
                    $arrAkhir['detail'][$value->kodeAkun]['sum_total'] = $value->debit - $value->kredit;
                }

                if (isset($arrAkhir['total'])) {
                    if (isset($arrAkhir['total'][$b['number']])) {
                        $arrAkhir['total'][$b['number']] += $value->debit - $value->kredit;
                    } else {
                        $arrAkhir['total'][$b['number']] = $value->debit - $value->kredit;
                    }
                } else {
                    $arrAkhir['total'][$b['number']] = !empty($value->debit) ? $value->debit - $value->kredit : 0;
                }
            }
        }


        $data['saldo_awal'] = $arrAwal;
        $data['saldo_periode'] = $arrPeriode;
        $data['saldo_akhir'] = $arrAkhir;
//
////    pd($akun_kas);
////    pd($arr);

        if (!empty($akun_kas)) {
            ksort($arr);
            ksort($arrAwal['detail']);
            ksort($arrAkhir['detail']);
            ksort($arrPeriode['detail']);
        }


        foreach ($arr as $key => $value) {
            if ($key == 'Aktivitas Operasi') {
                $arr[$key]['nama'] = 'AKTIVITAS OPERASI';
            } else if ($key == 'Investasi') {
                $arr[$key]['nama'] = 'AKTIVITAS INVESTASI';
            } else if ($key == 'Pendanaan') {
                $arr[$key]['nama'] = 'AKTIVITAS PENDANAAN';
            }
            ksort($arr[$key]['detail']);
            foreach ($value['detail'] as $keys => $values) {
//            foreach ($values['detail'] as $k => $v) {
                ksort($arr[$key]['detail'][$keys]['detail']);
//            }
            }
        }
//
//        print_die($arr);
    }

//    print_die($data);
//    print_die($arr);

    foreach ($arr as $key => $value) {
//        print_die($value);
        foreach ($arr_tanggal as $q => $w) {
            if (!isset($value['total'][$w['number']])) {
                $arr[$key]['total'][$w['number']] = 0;
            }
        }
        foreach ($value['detail'] as $k => $v) {
//            print_die($v);
            foreach ($arr_tanggal as $x => $y) {
                if (!isset($v['total'][$y['number']])) {
                    $arr[$key]['detail'][$k]['total'][$y['number']] = 0;
                }
            }
            foreach ($v['detail'] as $kk => $vv) {
                foreach ($arr_tanggal as $a => $b) {
                    if (!isset($vv['total'][$b['number']])) {
                        $arr[$key]['detail'][$k]['detail'][$kk]['total'][$b['number']] = 0;
                    }
                }
            }
        }
    }

//    print_die($arr);

    $data['arr_tanggal'] = $arr_tanggal;
    $data['jumlah_tanggal'] = count($arr_tanggal);

    if (isset($params['export']) && $params['export'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustomPeriode.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        header("Content-type: application/vnd.ms-excel");
        header("Content-Disposition: attachment;Filename=laporan-arus-kas.xls");
        echo $content;
    } elseif (isset($params['print']) && $params['print'] == 1) {
        $view = twigViewPath();
        $content = $view->fetch('laporan/arusKasCustomPeriode.html', [
            "data" => $data,
            "detail" => $arr,
            "css" => modulUrl() . '/assets/css/style.css',
        ]);
        echo $content;
        echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 5000);</script>';
    } else {
        return successResponse($response, ["data" => $data, "detail" => $arr]);
    }
});
