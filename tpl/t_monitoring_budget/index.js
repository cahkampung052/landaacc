app.controller('MonitoringBudgettingCtrl', function($scope, Data, $rootScope, $uibModal, Upload) {
    var tableStateRef;
    var control_link = "acc/t_monitoring_budget";
    var master = 'Monitoring Budget';
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.base_url = '';
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.is_create = false;
    $scope.form = {};
    $scope.form.m_kategori_pengajuan_id = {
        id: '2',
        nama: 'Operasional'
    }
    $scope.form.tahun = new Date();
    Data.get('acc/settingacc/url').then(function(response) {
        $scope.url = response.data.module_url;
    });
    Data.get("/acc/apppengajuan/getKategori").then(function(response) {
        $scope.listKategori = response.data;
        if ($scope.listKategori.length > 0) {
            $scope.form.m_kategori_pengajuan_id = $scope.listKategori[0];
            $scope.callServer(tableStateRef);
        }
    });
    $scope.modal = function(form) {
        form.tahun = $scope.form.tahun;
        form.m_kategori_pengajuan_id = $scope.form.m_kategori_pengajuan_id.id;
        var modalInstance = $uibModal.open({
            templateUrl: $scope.url + "/tpl/t_monitoring_budget/modalDetail.html",
            controller: "modalDetailCtrl",
            size: "lg",
            backdrop: "static",
            keyboard: false,
            resolve: {
                form: form,
            }
        });
        modalInstance.result.then(function(response) {
            if (response.data == undefined) {} else {}
        });
    }
    $scope.filterTahun = function(tahun) {
        $scope.form.tahun = tahun;
        $scope.callServer(tableStateRef);
    }
    $scope.master = master;
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 10;
        /** set offset and limit */
        var param = {
            tahun: $scope.form.tahun,
            kategori: $scope.form.m_kategori_pengajuan_id.id
        };
        /** set sort and order */
        if (tableState.sort.predicate) {
            param['sort'] = tableState.sort.predicate;
            param['order'] = tableState.sort.reverse;
        }
        /** set filter */
        if (tableState.search.predicateObject) {
            param['filter'] = tableState.search.predicateObject;
        }
        Data.get(control_link + '/index', param).then(function(response) {
            $scope.displayed = response.data.list;
            $scope.base_url = response.data.base_url;
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
});
app.controller("modalDetailCtrl", function($state, $scope, Data, $uibModalInstance, $rootScope, form) {
    $scope.form = form;
    $scope.total = 0;
    $scope.totalKegiatan = 0;
    Data.get('site/base_url').then(function(response) {
        $scope.url = response.data;
    });
    Data.get("acc/t_monitoring_budget/getDetail", {
        kategori_id: $scope.form.m_kategori_pengajuan_id,
        lokasi_id: $scope.form.id,
        tahun: $scope.form.tahun
    }).then(function(result) {
        $scope.listDetail = result.data.list;
        $scope.total = result.data.total;
        $scope.totalKegiatan = result.data.totalKegiatan;
    });
    $scope.exportDetail = function() {
        // console.log($scope.url);
        var param = {
            kategori_id: $scope.form.m_kategori_pengajuan_id,
            lokasi_id: $scope.form.id,
            tahun: moment($scope.form.tahun).format('YYYY-MM-DD'),
            is_export: 1
        };
        window.open($scope.url.base_url + "api/acc/t_monitoring_budget/getDetail?" + $.param(param), "_blank");
    }
    $scope.close = function() {
        $uibModalInstance.close({});
    };
});