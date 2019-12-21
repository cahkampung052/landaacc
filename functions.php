<?php

/**
 * Multi insert ke trans detail
 */
function insertTransDetail($data) {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    if (!empty($data)) {
        foreach ($data as $key => $value) {
            $db->insert("acc_trans_detail", $value);
        }
    }
}

/**
 * Set modul ACC URL
 */
function modulUrl() {
    $port = !empty($_SERVER['SERVER_PORT']) ? ":" . $_SERVER['SERVER_PORT'] : "";
    $a = "http://" . $_SERVER['SERVER_NAME'] . $port . $_SERVER['REQUEST_URI'];
    $a = str_replace($_SERVER['PATH_INFO'], '', $a);
    $a = substr($a, 0, strpos($a, "?"));
    return $a . "/" . config('MODUL_ACC_PATH');
}

/**
 * Set path untuk slim twig view
 */
function twigViewPath() {
    $view = new \Slim\Views\Twig(config('MODUL_ACC_PATH') . '/view');
    return $view;
}

function twigView() {
    $view = new \Slim\Views\Twig('views');
    return $view;
}

/**
 * Buat nested tree
 */
function buildTree($elements, $parentId = 0) {
    $branch = array();
    foreach ($elements as $element) {
        if ($element->parent_id == $parentId) {
            $children = buildTree($elements, $element->id);
            if ($children) {
                $element->children = $children;
            }
            $branch[$element->id] = $element;
        }
    }
    return $branch;
}

/**
 * Buat nested akun
 */
function buildTreeAkun($listAkun, $parentId = 0) {
    $branch = array();
    foreach ($listAkun as $key => $element) {
        $kode = str_replace(".", "", $element->kode);
        if ($element->parent_id == $parentId) {
            $children = buildTreeAkun($listAkun, $element->id);
            if ($children) {
                $element->children = $children;
            }
            $branch[$kode] = $element;
        }
    }
    // ksort($branch);
    return $branch;
}

/**
 * ubah id child jadi numerical array
 */
function buildFlatTreeId($tree, $ids = []) {
    $colName = 'id';
    $childColName = 'children';
    foreach ($tree as $element) {
        if (!isset($element->$colName)) {
            continue;
        }
        $ids[] = $element->$colName;
        if (isset($element->$childColName) && count($element->$childColName) > 0) {
            $ids = buildFlatTreeId($element->$childColName, $ids);
        }
    }
    return $ids;
}

/**
 * ubah child jadi flat array
 */
function flatten($arr) {
    $result = [];
    foreach ($arr as $key => $item) {
        $result[] = $item;
        if (isset($item->children)) {
            ksort($item->children);

            $result = array_merge($result, flatten($item->children));
        }
        unset($item->children);
    }
    return $result;
}

/**
 * Ambil semua child
 */
function getChildFlat($array, $parentId) {
    $tree = buildTree($array, $parentId);
    $child = flatten($tree);
    return $child;
}

/**
 * Ambil semua id child
 */
function getChildId($tabelName, $parentId) {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $db->select("*")->from($tabelName)->where("is_deleted", "=", 0);
    $data = $db->findAll();
    $tree = buildTree($data, $parentId);
    $child = buildFlatTreeId($tree);
    return $child;
}

/**
 * Ambil saldo awal
 */
function getSaldo($akunId, $lokasiId, $tanggalStart, $tanggalEnd) {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $db->select("sum(debit) as debit, sum(kredit) as kredit")
            ->from("acc_trans_detail")
            ->where("m_akun_id", "=", $akunId);
    if (!empty($lokasiId)) {
        if (is_array($lokasiId) && !empty($lokasiId)) {
            $db->customWhere("acc_trans_detail.m_lokasi_id in (" . implode(",", $lokasiId) . ")", "and");
        } else {
            $child = getChildId("acc_m_lokasi", $lokasiId);
            $child[] = $lokasiId;
            $db->customWhere("acc_trans_detail.m_lokasi_id in (" . implode(",", $child) . ")", "and");
        }
    }
    if (!empty($tanggalStart)) {
        $db->andWhere("acc_trans_detail.tanggal", ">=", $tanggalStart);
    }
    if (!empty($tanggalEnd)) {
        $db->andWhere("acc_trans_detail.tanggal", "<=", $tanggalEnd);
    }
    $model = $db->find();
    $debit = isset($model->debit) ? $model->debit : 0;
    $kredit = isset($model->kredit) ? $model->kredit : 0;
    return $debit - $kredit;
}

/**
 * Saldo Neraca berdasarkan tipe
 */
function getSaldoTipeNeraca($tanggal, $tipeAkun) {
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /**
     * Ambil transaksi
     */
    $sql->select("sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_m_akun.saldo_normal, acc_m_akun.id as m_akun_id")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe IN ('HARTA', 'KEWAJIBAN', 'MODAL')")
            ->groupBy("acc_m_akun.tipe")
            ->findAll();
    /**
     * Set parameter Tanggal
     */
    if (!empty($tanggal)) {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal);
    }
    $model = $sql->findAll();
    $arr = [];
    foreach ($model as $key => $value) {
        $subTotal = intval($value->debit) - intval($value->kredit);
        $arr[$value->tipe] = $subTotal * $value->saldo_normal;
    }
    /**
     * Ambil laba rugi
     */
    if ($tipeAkun == 'MODAL') {
        /**
         * Ambil laba rugi nominal
         */
        $labaRugi = getLabaRugiNominal(null, $tanggal, null);
        $arr[$tipeAkun] += $labaRugi['total'];
    }
    return $arr[$tipeAkun];
}

/**
 * Saldo Neraca
 */
function getSaldoNeraca($akunId, $lokasi, $tanggal) {
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if (!empty($lokasi)) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    }
    /**
     * Ambil transaksi
     */
    $sql->select("sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_m_akun.saldo_normal, acc_m_akun.id as m_akun_id")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe IN ('HARTA', 'KEWAJIBAN', 'MODAL')")
            ->groupBy("acc_m_akun.id")
            ->findAll();
    /**
     * Set parameter lokasi
     */
    if (!empty($lokasi)) {
        $sql->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND");
    }
    /**
     * Set parameter Akun
     */
    if (!empty($akunId)) {
        if (is_array($akunId) && !empty($akunId)) {
            $sql->customWhere("acc_trans_detail.m_akun_id IN (" . implode(",", $akunId) . ")", "AND");
        } else {
            $sql->customWhere("acc_trans_detail.m_akun_id = '" . $akunId . "'", "AND");
        }
    }
    /**
     * Set parameter Tanggal
     */
    if (!empty($tanggal)) {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal);
    }
    $model = $sql->findAll();
    $arr = [];
    foreach ($model as $key => $value) {
        $subTotal = intval($value->debit) - intval($value->kredit);
        $arr[$value->m_akun_id] = $subTotal * $value->saldo_normal;
    }
    /**
     * Ambil laba rugi nominal
     */
    $labaRugi = getLabaRugiNominal(null, $tanggal, null);
    if (isset($arr[$labaRugi['m_akun_id'][0]])) {
        $arr[$labaRugi['m_akun_id'][0]] += $labaRugi['total'];
    } else {
        $arr[$labaRugi['m_akun_id'][0]] = $labaRugi['total'];
    }
    return $arr;
}

/**
 * Nominal Laba Rugi
 */
function getLabaRugiNominal($tglStart = null, $tglEnd = null, $lokasi = null) {
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if ($lokasi != null) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    } else {
        $lokasi = $sql->findAll("select id from acc_m_lokasi where is_deleted = 0");
        $arrLok = [];
        foreach ($lokasi as $key => $value) {
            $arrLok[] = $value->id;
        }
        $lokasiId = implode(",", $arrLok);
    }
    /**
     * Ambil transaksi
     */
    $sql->select("sum(acc_trans_detail.debit) as debit, sum(acc_trans_detail.kredit) as kredit, acc_m_akun.saldo_normal, acc_m_akun.tipe")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe IN ('PENDAPATAN', 'PENDAPATAN DILUAR USAHA', 'BEBAN', 'BEBAN DILUAR USAHA')")
            ->groupBy("acc_m_akun.tipe")
            ->andWhere("is_tipe", "=", 0)
            ->andWhere("is_deleted", "=", 0)
            ->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND")
            ->findAll();
    /**
     * Set parameter tanggal
     */
    if (!empty($tglStart)) {
        $sql->andWhere("date(acc_trans_detail.tanggal)", ">=", $tglStart);
    }
    if (!empty($tglEnd)) {
        $sql->andWhere("date(acc_trans_detail.tanggal)", "<=", $tglEnd);
    }
    /**
     * hitung laba rugi
     */
    $model = $sql->findAll();
    $total = 0;
    foreach ($model as $key => $value) {
        $subTotal = ($value->debit - $value->kredit) * $value->saldo_normal;
        if ($value->tipe == "PENDAPATAN" || $value->tipe == "PENDAPATAN DILUAR USAHA") {
            $total += $subTotal;
        } else {
            $total -= $subTotal;
        }
    }
    /**
     * Ambil akun laba rugi
     */
    $pemetaan = getPemetaanAkun('Laba Rugi Berjalan');
    return [
        "m_akun_id" => $pemetaan,
        "total" => $total,
    ];
}

/**
 * Laba rugi
 */
function getLabaRugi($tanggal_start, $tanggal_end = null, $lokasi = null, $array = true) {
    $sql = new Cahkampung\Landadb(config('DB')['db']);
    /*
     * ambil child lokasi
     */
    if ($lokasi != null) {
        $lokasiId = getChildId("acc_m_lokasi", $lokasi);
        if (!empty($lokasiId)) {
            array_push($lokasiId, $lokasi);
            $lokasiId = implode(",", $lokasiId);
        } else {
            $lokasiId = $lokasi;
        }
    } else {
        $lokasi = $sql->findAll("select id from acc_m_lokasi where is_deleted = 0");
        $arrLok = [];
        foreach ($lokasi as $key => $value) {
            $arrLok[] = $value->id;
        }
        $lokasiId = implode(",", $arrLok);
    }
    $data['saldo_awal'] = 0;
    $data['total_saldo'] = 0;
    /*
     * ambil akun pengecualian
     */
    $akunPengecualian = getMasterSetting();
    $arrPengecualian = [];
    if (is_array($akunPengecualian) && !empty($akunPengecualian)) {
        foreach ($akunPengecualian->pengecualian_labarugi as $a => $b) {
            array_push($arrPengecualian, $b->m_akun_id->id);
        }
    }
    /**
     * Ambil transaksi di akun
     */
    $sql->select("
            SUM(debit) as debit, 
            SUM(kredit) as kredit,
            acc_m_akun.id,
            acc_m_akun.saldo_normal,
            acc_m_akun.tipe,
            acc_m_akun.parent_id
        ")
            ->from("acc_trans_detail")
            ->leftJoin("acc_m_akun", "acc_m_akun.id = acc_trans_detail.m_akun_id")
            ->customWhere("acc_m_akun.tipe in ('PENDAPATAN', 'PENDAPATAN DILUAR USAHA', 'BEBAN', 'BEBAN DILUAR USAHA')")
            ->customWhere("acc_trans_detail.m_lokasi_id IN($lokasiId)", "AND")
            ->groupBy("acc_m_akun.id")
            ->orderBy("acc_m_akun.is_tipe ASC, parent_id DESC, acc_m_akun.level DESC");
    /**
     * Filter tanggal
     */
    if ($tanggal_end != null) {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '>=', $tanggal_start)->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_end);
    } else {
        $sql->andWhere('date(acc_trans_detail.tanggal)', '<=', $tanggal_start);
    }
    /**
     * Filter pengecualian
     */
    if (!empty($arrPengecualian)) {
        $sql->customWhere("m_akun_id NOT INT (" . implode(",", $arrPengecualian) . ")", "And");
    }
    $trans = $sql->findAll();
    $arrTrans = [];
    $total = 0;
    foreach ($trans as $key => $value) {
        $arrTrans[$value->id] = (isset($arrTrans[$value->id]) ? $arrTrans[$value->id] : 0) + (intval($value->debit) - intval($value->kredit)) * $value->saldo_normal;
        $arrTrans[$value->parent_id] = (isset($arrTrans[$value->parent_id]) ? $arrTrans[$value->parent_id] : 0) + $arrTrans[$value->id];
        if ($value->tipe == "PENDAPATAN" || $value->tipe == "PENDAPATAN DILUAR USAHA") {
            $total += ($value->debit - $value->kredit) * $value->saldo_normal;
        } else {
            $total -= ($value->debit - $value->kredit) * $value->saldo_normal;
        }
    }
    /*
     * ambil akun (jika saldo 0 ikut ditampilkan)
     */
    $sql->select("id, nama, kode, tipe, level, is_tipe, parent_id")
            ->from("acc_m_akun")
            ->customWhere("tipe in ('PENDAPATAN', 'PENDAPATAN DILUAR USAHA', 'BEBAN', 'BEBAN DILUAR USAHA')")
            ->andWhere("is_deleted", "=", 0)
            ->orderBy("acc_m_akun.kode");
    $model = $sql->findAll();
    $listAkun = buildTreeAkun($model, 0);
    $arrModel = flatten($listAkun);
    $grandTotal = ['PENDAPATAN' => 0, 'BEBAN' => 0, 'PENDAPATAN DILUAR USAHA' => 0, 'BEBAN DILUAR USAHA' => 0];
    $arr = ['PENDAPATAN' => ['detail' => []], 'BEBAN' => ['detail' => []], 'PENDAPATAN_DILUAR_USAHA' => ['detail' => []], 'BEBAN_DILUAR_USAHA' => ['detail' => []]];
    /*
     * tanya adi ya, buat apa 
     */
    $testing = 0;
    foreach ($arrModel as $key => $value) {
        $total = (isset($arrTrans[$value->id]) ? intval($arrTrans[$value->id]) : 0);
        $tipe = str_replace(" ", "_", $value->tipe);
        $spasi = ($value->level == 1) ? '' : str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $value->level - 1);
        $fullName = $spasi . $value->kode . ' - ' . $value->nama;
        $arr[$tipe]['detail'][$key]['id'] = $value->id;
        $arr[$tipe]['detail'][$key]['kode'] = $value->kode;
        $arr[$tipe]['detail'][$key]['is_tipe'] = $value->is_tipe;
        $arr[$tipe]['detail'][$key]['parent_id'] = $value->parent_id;
        $arr[$tipe]['detail'][$key]['nama'] = ($value->is_tipe == 0) ? $fullName : "<b>" . $fullName . "</b>";
        $arr[$tipe]['detail'][$key]['nama2'] = $value->nama;
        $arr[$tipe]['detail'][$key]['nominal'] = $total;
        if ($value->is_tipe == 0) {
            $arr[$tipe]['total'] = (isset($arr[$tipe]['total']) ? $arr[$tipe]['total'] : 0) + $total;
            $grandTotal[$value->tipe] += $total;
        }
    }
    ksort($arr['PENDAPATAN']['detail']);
    ksort($arr['BEBAN']['detail']);
    ksort($arr['PENDAPATAN_DILUAR_USAHA']['detail']);
    ksort($arr['BEBAN_DILUAR_USAHA']['detail']);
    if ($array) {
        return ["data" => $arr, "total" => $grandTotal];
    } else {
        return $grandTotal['PENDAPATAN'] + $grandTotal['PENDAPATAN DILUAR USAHA'] - $grandTotal['BEBAN'] - $grandTotal['BEBAN DILUAR USAHA'];
    }
}

function getPemetaanAkun($tipe = '') {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $arrAkun = [0 => 0];
    $db->select("*")
            ->from("acc_m_akun_peta");
    if (!empty($tipe)) {
        $db->where("type", "=", $tipe);
    }
    $akun = $db->findAll();
    foreach ($akun as $key => $value) {
        if (isset($value->is_multiple) && $value->is_multiple == 1) {
            $arrAkun[$value->type] = json_decode($value->m_akun_id);
        } else {
            $arrAkun[$value->type] = [0 => $value->m_akun_id];
        }
    }
    if (!empty($tipe)) {
        return isset($arrAkun[$tipe]) ? $arrAkun[$tipe] : [0 => 0];
    } else {
        return $arrAkun;
    }
}

function getMasterSetting() {
    $db = new Cahkampung\Landadb(config('DB')['db']);
    $data = $db->select("*")
            ->from("acc_m_setting")
            ->find();
    $data->pengecualian_neraca = json_decode($data->pengecualian_neraca);
    $data->pengecualian_labarugi = json_decode($data->pengecualian_labarugi);
    return $data;
}

function getSessionLokasi() {
    $cabang = [];
    foreach ($_SESSION['user']['lokasi'] as $val) {
        $cabang[] = $val->id;
    }
    $return = implode(",", $cabang);
    return $return;
}

function generateNoTransaksi($type, $unker, $preffix = null, $bulan = null, $tahun = null) {
    $db = config('DB');
    $db = new Cahkampung\Landadb($db['db']);
    $custom = getMasterSetting();
    if ($bulan == null) {
        $bulan = date("m");
    }
    if ($tahun == null) {
        $tahun = date("Y");
    }
    if (!isset($custom->digit_kode) || empty($custom->digit_kode)) {
        $custom->digit_kode = -5;
    } else {
        $custom->digit_kode = (int) -$custom->digit_kode;
    }

    if (!isset($custom->reset_kode) || empty($custom->reset_kode)) {
        $custom->reset_kode = 'tahunan';
    }

    $no_transaksi = '';
    if ($type == 'penerimaan') {
        $string = "";
        if (strpos($custom->format_pemasukan, "BMKK") !== false) {
            $getpreffix = $db->select("*")->from("acc_m_akun")->where("id", "=", $preffix)->find();
            if ($getpreffix) {
                if ($getpreffix->nama == 'CASH ON HAND') {
                    $string = "KM";
                } else {
                    $fitst_char = strtoupper(substr($getpreffix->nama, 0, 1));
                    $string = $fitst_char . "M";
                }
            }
        }
        if ($custom->reset_kode == 'bulanan') {
            if (!empty($preffix) && strpos($custom->format_pemasukan, "BMKK") !== false) {
                $cek = $db->find("select no_transaksi from acc_pemasukan WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$string}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_pemasukan WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "'  order by no_transaksi desc");
            }
        } else {
            if (!empty($preffix) && strpos($custom->format_pemasukan, "BMKK") !== false) {
                $cek = $db->find("select no_transaksi from acc_pemasukan WHERE YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$string}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_pemasukan WHERE YEAR(tanggal) = '" . $tahun . "' order by no_transaksi desc");
            }
        }

        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, $custom->digit_kode)) + 1;
        $no_urut = substr('00000' . $urut, $custom->digit_kode);
        $no_transaksi = $custom->format_pemasukan;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
        $no_transaksi = str_replace("BMKK", $string, $no_transaksi);
    } elseif ($type == 'pengeluaran') {
        $string = "";
        if (strpos($custom->format_pemasukan, "BMKK") !== false) {
            $getpreffix = $db->select("*")->from("acc_m_akun")->where("id", "=", $preffix)->find();
            if ($getpreffix) {
                if ($getpreffix->nama == 'CASH ON HAND') {
                    $string = "KM";
                } else {
                    $fitst_char = strtoupper(substr($getpreffix->nama, 0, 1));
                    $string = $fitst_char . "M";
                }
            }
        }
        if ($custom->reset_kode == 'bulanan') {
            if (!empty($preffix) && strpos($custom->format_pengeluaran, "BKKK") !== false) {
                $cek = $db->find("select no_urut, no_transaksi from acc_pengeluaran WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$preffix}%' order by no_urut desc");
            } else {
                $cek = $db->find("select no_urut, no_transaksi from acc_pengeluaran WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' order by no_urut desc");
            }
        } else {
            if (!empty($preffix) && strpos($custom->format_pengeluaran, "BKKK") !== false) {
                $cek = $db->find("select no_urut, no_transaksi from acc_pengeluaran WHERE YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '%{$preffix}%' order by no_urut desc");
            } else {
                $cek = $db->find("select no_urut, no_transaksi from acc_pengeluaran WHERE YEAR(tanggal) = '" . $tahun . "' order by no_urut desc");
            }
        }

        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, $custom->digit_kode)) + 1;
        $no_urut = substr('00000' . $urut, $custom->digit_kode);
        $no_transaksi = $custom->format_pengeluaran;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
        $no_transaksi = str_replace("BKKK", $string, $no_transaksi);
    } elseif ($type == 'transfer') {
        if ($custom->reset_kode == 'bulanan') {
            if (!empty($preffix)) {
                $cek = $db->find("select no_transaksi from acc_transfer WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$preffix}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_transfer WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' order by no_transaksi desc");
            }
        } else {
            if (!empty($preffix)) {
                $cek = $db->find("select no_transaksi from acc_transfer WHERE YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$preffix}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_transfer WHERE YEAR(tanggal) = '" . $tahun . "' order by no_transaksi desc");
            }
        }

        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, $custom->digit_kode)) + 1;
        $no_urut = substr('00000' . $urut, $custom->digit_kode);
        $no_transaksi = $custom->format_transfer;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'jurnal') {
        if ($custom->reset_kode == 'bulanan') {
            if (!empty($preffix)) {
                $cek = $db->find("select no_transaksi from acc_jurnal WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$preffix}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_jurnal WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' order by no_transaksi desc");
            }
        } else {
            if (!empty($preffix)) {
                $cek = $db->find("select no_transaksi from acc_jurnal WHERE YEAR(tanggal) = '" . $tahun . "' AND no_transaksi LIKE '{$preffix}%' order by no_transaksi desc");
            } else {
                $cek = $db->find("select no_transaksi from acc_jurnal WHERE YEAR(tanggal) = '" . $tahun . "' order by no_transaksi desc");
            }
        }

        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, $custom->digit_kode)) + 1;
        $no_urut = substr('00000' . $urut, $custom->digit_kode);
        $no_transaksi = $custom->format_jurnal;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'pengajuan') {
        if ($custom->reset_kode == 'bulanan') {
            if (!empty($preffix)) {
                $cek = $db->find("select no_urut, no_proposal from acc_t_pengajuan WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' AND no_proposal LIKE '{$preffix}%' order by no_urut desc");
            } else {
                $cek = $db->find("select no_urut, no_proposal from acc_t_pengajuan WHERE MONTH(tanggal) = '" . $bulan . "' AND YEAR(tanggal) = '" . $tahun . "' order by no_urut desc");
            }
        } else {
            if (!empty($preffix)) {
                $cek = $db->find("select no_urut, no_proposal from acc_t_pengajuan WHERE YEAR(tanggal) = '" . $tahun . "' AND no_proposal LIKE '{$preffix}%' order by no_urut desc");
            } else {
                $cek = $db->find("select no_urut, no_proposal from acc_t_pengajuan WHERE YEAR(tanggal) = '" . $tahun . "' order by no_urut desc");
            }
        }

        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_proposal, $custom->digit_kode)) + 1;
        $no_urut = substr('00000' . $urut, $custom->digit_kode);
        $no_transaksi = $custom->format_pengajuan;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'kasbon') {
        $cek = $db->find("select no_transaksi, no_urut from acc_kasbon order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $tahun . "/" . $bulan . "/KSBN/" . $no_urut;
    } elseif ($type == 'pembayaran_kasbon') {
        $cek = $db->find("select no_transaksi, no_urut from acc_bayar_kasbon order by no_urut desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->no_transaksi, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $tahun . "/" . $bulan . "/" . $unker . "/BYRKSBN/" . $no_urut;
    } elseif ($type == 'pembayaran_honor') {
        $cek = $db->find("select kode from acc_honor_dokter order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "HD/" . $tahun . "/" . $no_urut;
    } elseif ($type == 'customer') {
        $cek = $db->find("select kode from acc_m_kontak where jenis = 'lain' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "CUST" . date("y") . "" . $no_urut;
    } elseif ($type == 'customerAll') {
        $cek = $db->find("select kode from acc_m_kontak where jenis = 'customer' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "CUST" . date("y") . "" . $no_urut;
    } elseif ($type == 'supplier') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'supplier' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "VND" . $tahun . "" . $no_urut;
    } elseif ($type == 'stok_masuk') {
        $cek = $db->find("select kode from inv_stok_masuk order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "PI/" . $bulan . "/" . $tahun . "/" . $no_urut;
    } elseif ($type == 'saldo_hutang') {
        $cek = $db->find("select kode from acc_saldo_hutang order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "HT/" . $bulan . "/" . $tahun . "/" . $no_urut;
    } elseif ($type == 'saldo_piutang') {
        $cek = $db->find("select kode from acc_saldo_piutang order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "PT/" . $bulan . "/" . $tahun . "/" . $no_urut;
    } elseif ($type == 'asuransi') {
        $cek = $db->find("select kode from m_asuransi order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -4)) + 1;
        $no_urut = substr('0000' . $urut, -4);
        $no_transaksi = "ASR" . "/" . $no_urut;
    }
    //AFU
    else if ($type == 'retur_penjualan') {
        $cek = $db->find("select kode from inv_retur_penjualan order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "RJ" . date("y") . "/" . $no_urut;
    } else if ($type == 'pembelian') {
        $cek = $db->find("select kode from inv_pembelian where inv_proses_akhir_id is null order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = $custom->format_pembelian;
        $no_transaksi = str_replace("TAHUN", date("y", strtotime($tahun)), $no_transaksi);
        $no_transaksi = str_replace("BULAN", $bulan, $no_transaksi);
        $no_transaksi = str_replace("KODEUNIT", $unker, $no_transaksi);
        $no_transaksi = str_replace("NOURUT", $no_urut, $no_transaksi);
    } elseif ($type == 'jasa') {
        $cek = $db->find("select kode from acc_m_kontak where type in('angkutan', 'pelabuhan', 'pengurusan dokumen', 'pengelolaan gudang', 'lain-lain') order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "JS" . $tahun . "" . $no_urut;
    } elseif ($type == 'proses akhir') {
        $cek = $db->find("select kode from inv_proses_akhir order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "PA" . $no_urut;
    } elseif ($type == 'stok_opname') {
        $cek = $db->find("select kode from inv_stok_opname order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "SO" . "/" . $unker . "/" . $no_urut;
    } elseif ($type == 'afu_customer') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'customer' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "CUST" . date("y") . "" . $no_urut;
    } elseif ($type == 'afu_customer') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'customer' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "CUST" . date("y") . "" . $no_urut;
    } elseif ($type == 'afu_supplier') {
        $cek = $db->find("select kode from acc_m_kontak where type = 'supplier' order by kode desc");
        $urut = (empty($cek)) ? 1 : ((int) substr($cek->kode, -5)) + 1;
        $no_urut = substr('00000' . $urut, -5);
        $no_transaksi = "VND" . date("y") . "" . $no_urut;
    }
    //END AFU
    echo $no_transaksi;
    die;
    return $no_transaksi;
}

function tableUser() {
    if (config('TABLE_USER') == "") {
        return "acc_m_user";
    } else {
        return config('TABLE_USER');
    }
}

function sortKode($kode) {
    $listkode = explode(".", $kode);
    foreach ($listkode as $k => $v) {
        $listkode[$k] = substr('000' . $v, -3);
    }

    return implode(".", $listkode);
}
