<?php
/**
 * Validasi
 * @param  array $data
 * @param  array $custom
 * @return array
 */
function validasi($data, $custom = array())
{
    $validasi = array(
        "nama"       => "required",
        "username"   => "required",
        "m_roles_id" => "required",
    );
    GUMP::set_field_name("m_roles_id", "Hak Akses");
    $cek = validate($data, $validasi, $custom);
    return $cek;
}
/**
 * Ambil semua user aktif tanpa pagination
 */
$app->get("/acc/appuser/getAll", function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db     = $this->db;
    $db->select("*")
        ->from($tableuser)
        ->where("is_deleted", "=", 0);
    if (isset($params["nama"]) && !empty($params["nama"])) {
        $db->where("nama", "LIKE", $params["nama"]);
    }
    $models = $db->findAll();
    return successResponse($response, $models);
});
/**
 * Ambil data user untuk update profil
 */
$app->get("/acc/appuser/view", function ($request, $response) {
    $tableuser = tableUser();
    $db   = $this->db;
    $data = $db->find("select * from " .$tableuser. " where id = '" . $_SESSION["user"]["id"] . "'");
    unset($data->password);
    return successResponse($response, $data);
});
/**
 * Ambil semua list user
 */
$app->get("/acc/appuser/index", function ($request, $response) {
    $params = $request->getParams();
    $tableuser = tableUser();
    $db     = $this->db;
    $db->select($tableuser.".*, acc_m_roles.nama as hakakses")
        ->from($tableuser)
        ->join("left join", "acc_m_roles", $tableuser.".m_roles_id = acc_m_roles.id");
    /**
     * Filter
     */
    if (isset($params["filter"])) {
        $filter = (array) json_decode($params["filter"]);
        foreach ($filter as $key => $val) {
            if ($key == "nama") {
                $db->where($tableuser.".nama", "LIKE", $val);
            } else if ($key == "is_deleted") {
                $db->where($tableuser.".is_deleted", "=", $val);
            } else {
                $db->where($key, "LIKE", $val);
            }
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
    $models    = $db->findAll();
    $totalItem = $db->count();
    foreach ($models as $key => $val) {
        $val->m_roles_id = (string) $val->m_roles_id;
        $val->lokasi = json_decode($val->lokasi);
    }
    return successResponse($response, ["list" => $models, "totalItems" => $totalItem]);
});
/**
 * save user
 */
$app->post("/acc/appuser/save", function ($request, $response) {
    $data = $request->getParams();
    $tableuser = tableUser();
    $db   = $this->db;

    if (isset($data["id"])) {
        /**
         * Update user
         */
        if (!empty($data["password"])) {
            $data["password"] = sha1($data["password"]);
        } else {
            unset($data["password"]);
        }
        $validasi = validasi($data);
    } else {
        /**
         * Buat user baru
         */
        $data["password"] = isset($data["password"]) ? sha1($data["password"]) : "";
        $validasi         = validasi($data, ["password" => "required"]);
    }

    if ($validasi === true) {
        try {
            $data['lokasi'] = json_encode($data['lokasi']);
            if (isset($data["id"])) {
                $model = $db->update($tableuser, $data, ["id" => $data["id"]]);
            } else {
                $model = $db->insert($tableuser, $data);
            }
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["Terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
/**
 * save status user
 */
$app->post("/acc/appuser/saveStatus", function ($request, $response) {
    $data = $request->getParams();
    $tableuser = tableUser();
    $db   = $this->db;
    unset($data["password"]);
    $validasi = validasi($data);
    if ($validasi === true) {
        try {
            $data["lokasi"] = json_encode($data["lokasi"]);
            $model = $db->update($tableuser, $data, ["id" => $data["id"]]);
            return successResponse($response, $model);
        } catch (Exception $e) {
            return unprocessResponse($response, ["Terjadi masalah pada server"]);
        }
    }
    return unprocessResponse($response, $validasi);
});
