<?php

/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array()) {
    $validasi = array(
        "m_lokasi_id" => "required",
//        "no_proposal" => "required"
    );
    $cek = validate($data, $validasi, $custom);
    return $cek;
}

/*
 * ambil pengajuan
 */
$app->get("/acc/apppengajuan/getAll", function ($request, $response) {
    $params = $request->getParams();
//    print_r($params);die();
    $db = $this->db;
    $db->select("acc_t_pengajuan.*, acc_m_lokasi.nama as namaLokasi, acc_m_lokasi.kode as kodeLokasi")
            ->from("acc_t_pengajuan")
            ->join("JOIN", "acc_m_lokasi", "acc_m_lokasi.id = acc_t_pengajuan.m_lokasi_id")
            ->join("JOIN", "acc_approval_pengajuan", "acc_approval_pengajuan.t_pengajuan_id = acc_t_pengajuan.id")
            ->groupBy("acc_t_pengajuan.id")
            ->customWhere("(acc_approval_pengajuan.acc_m_user_id = {$_SESSION['user']['id']} OR acc_t_pengajuan.created_by = {$_SESSION['user']['id']})");
    if (isset($params['id'])) {
        $db->andWhere("acc_t_pengajuan.id", "=", $params['id']);
    }
    if (isset($params['no_proposal'])) {
        $db->andWhere("acc_t_pengajuan.no_proposal", "=", $params['no_proposal']);
    }
    $models = $db->findAll();
    foreach ($models as $key => $val) {
        $models[$key] = (array) $val;
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        $models[$key]['tanggal_formated'] = date("d-m-Y H:i", strtotime($val->tanggal));
    }
    return successResponse($response, $models);
});

/**
 * Ambil detail t pengajuan
 */
$app->get("/acc/apppengajuan/view", function ($request, $response) {
    $params = $request->getParams();
    $db = $this->db;
    $db->select("acc_t_pengajuan_det.*, acc_m_akun.nama as namaAkun, acc_m_akun.kode as kodeAkun")
            ->from("acc_t_pengajuan_det")
            ->join("JOIN", "acc_m_akun", "acc_m_akun.id = acc_t_pengajuan_det.m_akun_id")
            ->where("t_pengajuan_id", "=", $params["t_pengajuan_id"]);
    $models = $db->findAll();

    foreach ($models as $key => $val) {
        $val->m_akun_id = ["id" => $val->m_akun_id, "nama" => $val->namaAkun, "kode" => $val->kodeAkun];
    }

    return successResponse($response, $models);
});

/*
 * get t_acc_pengajuan
 */
$app->get("/acc/apppengajuan/getAcc", function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("acc_approval_pengajuan.*, " .$tableuser. ".nama as namaUser")
            ->from("acc_approval_pengajuan")
            ->join("JOIN", $tableuser, $tableuser.".id = acc_approval_pengajuan.acc_m_user_id")
            ->where("t_pengajuan_id", "=", $params['t_pengajuan_id'])
            ->orderBy("acc_approval_pengajuan.level");
    $models = $db->findAll();
    foreach ($models as $key => $val) {
        $val->acc_m_user_id = ["id" => $val->acc_m_user_id, "nama" => $val->namaUser];
        $val->status = ucfirst($val->status);
    }
    return successResponse($response, $models);
});

/**
 * Ambil semua t pengajuan
 */
$app->get("/acc/apppengajuan/index", function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("acc_t_pengajuan.*, acc_m_lokasi.nama as namaLokasi, acc_m_lokasi.kode as kodeLokasi, " .$tableuser. ".nama as namaUser")
            ->from("acc_t_pengajuan")
            ->join("JOIN", "acc_m_lokasi", "acc_m_lokasi.id = acc_t_pengajuan.m_lokasi_id")
            ->join("JOIN", $tableuser, $tableuser.".id = acc_t_pengajuan.created_by")
            ->orderBy("tanggal DESC");

    /**
     * Filter
     */
    if (isset($params["filter"])) {
        $filter = (array) json_decode($params["filter"]);
        foreach ($filter as $key => $val) {
            $db->where($key, "LIKE", $val);
        }
    }
    /**
     * Set limit dan offset
     */
    if (isset($params["limit"]) && !empty($params["limit"])) {
        $db->limit($params["limit"]);
    }
    if (isset($params["offset"]) && !empty($params["offset"])) {
        $db->offset($params["offset"]);
    }
    $models = $db->findAll();
    $totalItem = $db->count();

    foreach ($models as $key => $val) {
        $models[$key] = (array) $val;
        $models[$key]['m_lokasi_id'] = ["id" => $val->m_lokasi_id, "nama" => $val->namaLokasi, "kode" => $val->kodeLokasi];
        $models[$key]['tanggal_formated'] = date("d-m-Y H:i", strtotime($val->tanggal));
        $models[$key]['created_formated'] = $val->namaUser;
        $models[$key]['status'] = ucfirst($val->status);
        $models[$key]['levelapproval'] = intval($val->levelapproval);

        /*
         * ambil sisa approve dari acc_approval_pengajuan
         */
//        $sisa = $db->select("*")->from("acc_approval_pengajuan")->where("t_pengajuan_id", "=", $val->id)->where("status", "!=", "approved")->count();
//        $models[$key]['sisa_approval'] = $sisa;


        /*
         * ambil level dari t_acc_pengajuan
         */
        $acc = $db->select("level")->from("acc_approval_pengajuan")->where("t_pengajuan_id", "=", $val->id)->where("acc_m_user_id", "=", $_SESSION['user']['id'])->find();
        if ($acc) {
            $models[$key]['level'] = intval($acc->level);
        } else {
            if ($val->created_by != $_SESSION['user']['id']) {
                unset($models[$key]);
            }
        }
    }

    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});
/**
 * Save t pengajuan
 */
$app->post("/acc/apppengajuan/save", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
//    print_r($data);die();
    $validasi = validasi($data["data"]);
    if ($validasi === true) {

        
        /**
         * Generate no_proposal
         */
        $kode = generateNoTransaksi("pengajuan", $data['data']['m_lokasi_id']['kode']);
        $urut = (empty($kode)) ? 1 : ((int) substr($kode, -5));
//        echo $kode;die();
        $data["data"]["m_lokasi_id"] = $data["data"]["m_lokasi_id"]["id"];
        $tanggal = $data["data"]["tanggal"];
        $data["data"]["tanggal"] = date("Y-m-d H:i", strtotime($tanggal));
        $data["data"]["lokasi_waktu"] .= " " . date("H:i");
        try {

            if (isset($data["data"]["id"]) && !empty($data["data"]["id"])) {
                $model = $db->update("acc_t_pengajuan", $data["data"], ["id" => $data["data"]["id"]]);
                $db->delete("acc_t_pengajuan_det", ["t_pengajuan_id" => $data["data"]["id"]]);
                $db->delete("acc_approval_pengajuan", ["t_pengajuan_id" => $data["data"]["id"]]);
            } else {
                $data["data"]["no_proposal"] = $kode;
                $data["data"]["no_urut"] = $urut;
                unset($data["data"]["id"]);
                unset($data["data"]["levelapproval"]);
                $checkproposal = $db->select("*")
                        ->from("acc_t_pengajuan")
                        ->where("no_proposal", "=", $data["data"]["no_proposal"])
                        ->find();
                if ($checkproposal) {
                    return unprocessResponse($response, ["No proposal sudah ada"]);
                    die();
                }
                
                
                $model = $db->insert("acc_t_pengajuan", $data["data"]);
            }



            /**
             * Simpan detail
             */
            if (isset($data["detail"]) && !empty($data["detail"])) {
                foreach ($data["detail"] as $key => $val) {
                    $detail["id"] = isset($val["id"]) && !empty($val["id"]) ? $val["id"] : '';
                    $detail["m_akun_id"] = isset($val["m_akun_id"]['id']) ? $val["m_akun_id"]['id'] : '';
                    $detail["keterangan"] = isset($val["keterangan"]) ? $val["keterangan"] : '';
                    $detail["jenis_satuan"] = isset($val["jenis_satuan"]) ? $val["jenis_satuan"] : '';
                    $detail["harga_satuan"] = isset($val["harga_satuan"]) ? $val["harga_satuan"] : '';
                    $detail["jumlah"] = isset($val["jumlah"]) ? $val["jumlah"] : '';
                    $detail["sub_total"] = isset($val["sub_total"]) ? $val["sub_total"] : '';
                    $detail["t_pengajuan_id"] = $model->id;
                    $db->insert("acc_t_pengajuan_det", $detail);
                }
            }

            /*
             * Simpan t_acc_pengajuan
             */
            $getsetting = $db->select("*")->from("acc_m_setting_approval")
                    ->where("min", "<=", $data['data']['jumlah_perkiraan'])
                    ->where("max", ">=", $data['data']['jumlah_perkiraan'])
                    ->where("tipe", "=", $data['data']['tipe'])
                    ->findAll();
            if ($getsetting) {
                foreach ($getsetting as $key => $val) {
                    $insert['t_pengajuan_id'] = $model->id;
                    $insert['acc_m_user_id'] = $val->acc_m_user_id;
                    $insert['sebagai'] = $val->sebagai;
                    $insert['level'] = $val->level;

                    $db->insert("acc_approval_pengajuan", $insert);
                }
            }
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["terjadi kesalahan pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
/**
 * Hapus t pengajuan
 */
$app->post("/acc/apppengajuan/hapus", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
    try {
        $model = $db->delete("acc_t_pengajuan", ["id" => $data["id"]]);
        $modelDetail = $db->delete("acc_t_pengajuan_det", ["t_pengajuan_id" => $data["id"]]);
        $modelAcc = $db->delete("acc_approval_pengajuan", ["t_pengajuan_id" => $data["id"]]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["terjadi masalah pada server"]);
    }
    return unprocessResponse($response, $validasi);
});

/**
 * approve / tolak t pengajuan
 */
$app->post("/acc/apppengajuan/status", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;
//    print_r($data);die();
    try {
        $update['status'] = $data['status'];
        $update['catatan'] = isset($data['catatan']) ? $data['catatan'] : "";
        if ($data['status'] == "open") {
            $update['levelapproval'] = $data['data']['level'] + 1;
        }
        $model = $db->update("acc_t_pengajuan", $update, ["id" => $data["data"]["id"]]);
        if($data['status'] == "open")
            $statusapproval = "approved";
        if($data['status'] == "canceled")
            $statusapproval = "canceled";
        if($data['status'] == "rejected")
            $statusapproval = "rejected";
        
        $models = $db->update("acc_approval_pengajuan", ["status" => $statusapproval], ["t_pengajuan_id" => $data["data"]["id"], "acc_m_user_id" => $_SESSION["user"]["id"]]);

        if ($statusapproval == "approved") {
            /*
             * cek jika masih kurang approve
             */
            $db->select("*")
                    ->from("acc_approval_pengajuan")
                    ->where("t_pengajuan_id", "=", $data["data"]["id"])
                    ->where("acc_m_user_id", "=", $_SESSION["user"]["id"]);
            $approved = $db->where("status", "=", "approved")->count();
            $all = $db->count();

            /*
             * if (sudah approve semua), update t_pengajuan jadi approved
             */
            if ($approved == $all) {
                $model = $db->update("acc_t_pengajuan", ["approval" => $data['data']['level'], "status" => "approved"], ["id" => $data["data"]["id"]]);
            }
        }


        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, $e);
    }
    return unprocessResponse($response, $validasi);
});

/*
 * cetak
 */
$app->get("/acc/apppengajuan/printPengajuan", function ($request, $response) {
    $data = $request->getParams();
    $tableuser = tableUser();
    $db = $this->db;
    $db->select("*")->from("acc_t_pengajuan_det")->where("t_pengajuan_id", "=", $data['id']);
    $detail = $db->findAll();
    foreach ($detail as $key => $val) {
        $val->no = $key + 1 . ".";
    }
    $db->select("acc_approval_pengajuan.*, " .$tableuser. ".nama")->from("acc_approval_pengajuan")->join("JOIN", $tableuser, $tableuser.".id = acc_approval_pengajuan.acc_m_user_id")->where("t_pengajuan_id", "=", $data['id']);
    $acc = $db->findAll();
//    echo "<pre>", print_r($data), "</pre>";die();
    $a = getMasterSetting();
    $template = $a->print_pengajuan;
//    print_r($template);die();
    $template = str_replace("{start_detail}", "{%for key, val in detail%}", $template);
    $template = str_replace("{end}", "{%endfor%}", $template);
    $template = str_replace("{start_acc}", "{%for key, val in acc%}", $template);
    $template = str_replace("<td></td>", "", $template);
    $view = twigViewPath();
    $content = $view->fetchFromString($template, [
        "data" => $data,
        "detail" => (array) $detail,
        "acc" => (array) $acc
//            "css" => modulUrl() . '/assets/css/style.css',
    ]);
    $content = str_replace("<td></td>", "", $content);
    $content = str_replace("<td>&nbsp;</td>", "", $content);
    echo $content;
    echo '<script type="text/javascript">window.print();setTimeout(function () { window.close(); }, 500);</script>';
});


/*
 * template print
 */
$app->get("/acc/apppengajuan/getTemplate", function ($request, $response) {
    $a = getMasterSetting();
    return successResponse($response, $a->print_pengajuan);
});

$app->post("/acc/apppengajuan/saveTemplate", function ($request, $response) {
    $data = $request->getParams();
    $db = $this->db;

    try {
        $model = $db->update("acc_m_setting", $data, ["id" => 1]);
        return successResponse($response, $model);
    } catch (Exception $e) {
        return unprocessResponse($response, ["Terjadi kesalahan pada server"]);
    }
});
