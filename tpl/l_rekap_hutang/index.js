app.controller('l_rekaphutangCtrl', function($scope, Data, $rootScope) {
    var tableStateRef;
    var control_link = "acc/l_rekap_hutang";
    var master = 'Laporan Rekap Hutang';
    $scope.master = master;
    $scope.formTitle = '';
    $scope.base_url = '';
    $scope.form = {};
    $scope.form.tanggal = {
        endDate: moment().add(1, 'M'),
        startDate: moment()
    };
    /**
     * Ambil list semua akun
     */
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.listAkun = data.data.list;
        if ($scope.listAkun.length > 0) {
            $scope.form.m_akun_id = $scope.listAkun[0];
        }
    });
    /**
     * Ambil semua lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function(response) {
        $scope.listLokasi = response.data.list;
        if ($scope.listLokasi.length > 0) {
            $scope.form.m_lokasi_id = $scope.listLokasi[0];
        }
    });
    
    /*
     * ambil supplier
     */
    Data.get('acc/m_supplier/getSupplier').then(function(response) {
        $scope.listSupplier = response.data.list;
        if ($scope.listSupplier.length > 0) {
            $scope.form.m_supplier_id = $scope.listSupplier[0];
        }
    });
    
    /**
     * Ambil laporan dari server
     */
    $scope.view = function(is_export, is_print) {
        $scope.mulai = moment($scope.form.tanggal.startDate).format('DD-MM-YYYY');
        $scope.selesai = moment($scope.form.tanggal.endDate).format('DD-MM-YYYY');
        var param = {
            export: is_export,
            print: is_print,
            m_lokasi_id: $scope.form.m_lokasi_id.id,
            nama_lokasi: $scope.form.m_lokasi_id.nama,
            m_akun_id: $scope.form.m_akun_id.id,
            nama_akun : $scope.form.m_akun_id.nama,
            startDate: moment($scope.form.tanggal.startDate).format('YYYY-MM-DD'),
            endDate: moment($scope.form.tanggal.endDate).format('YYYY-MM-DD'),
        };
        if (is_export == 0 && is_print == 0) {
            Data.get(control_link + '/laporan', param).then(function(response) {
                if (response.status_code == 200) {
                    $scope.data = response.data.data;
                    $scope.detail = response.data.detail;
                    $scope.tampilkan = true;
                } else {
                    $rootScope.alert("Terjadi Kesalahan", setErrorMessage(response.errors), "error");
                    $scope.tampilkan = false;
                }
            });
        } else {
            window.open("api/acc/l_rekap_hutang/laporan?" + $.param(param), "_blank");
        }
    };
});