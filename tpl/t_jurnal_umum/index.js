app.controller('jurnalCtrl', function($scope, Data, $rootScope, $uibModal, Upload, FileUploader) {
    var tableStateRef;
    var control_link = "acc/t_jurnal_umum";
    $scope.formTitle = '';
    $scope.displayed = [];
    $scope.gambar = [];
    $scope.is_edit = false;
    $scope.is_view = false;
    $scope.urlfoto = "api/file/jurnal-umum/";
    /*
     * ambil akun untuk detail
     */
    Data.get('acc/m_akun/akunDetail').then(function(data) {
        $scope.akunAll = data.data.list;
    });
    /*
     * ambil lokasi
     */
    Data.get('acc/m_lokasi/getLokasi').then(function(response) {
        $scope.listLokasi = response.data.list;
    });
    /**
     * Ambil setting tanggal
     */
    Data.get('acc/m_akun/getTanggalSetting').then(function(response) {
        $scope.tanggal_setting = response.data.tanggal;
        $scope.options = {
            minDate: new Date(response.data.tanggal),
        };
    });
    /*
     *  FileUploader
     */
    var uploader = $scope.uploader = new FileUploader({
        url: Data.base + 'acc/t_jurnal_umum/upload/bukti',
        formData: [],
        removeAfterUpload: true,
    });
    $scope.uploadGambar = function(form) {
        $scope.uploader.uploadAll();
    };
    uploader.filters.push({
        name: 'imageFilter',
        fn: function(item) {
            var type = '|' + item.type.slice(item.type.lastIndexOf('/') + 1) + '|';
            var x = '|jpg|png|jpeg|bmp|gif|'.indexOf(type) !== -1;
            if (!x) {
                $rootScope.alert("Terjadi Kesalahan", "Jenis gambar tidak sesuai", "error");
            }
            return x;
        }
    });
    uploader.filters.push({
        name: 'sizeFilter',
        fn: function(item) {
            var xz = item.size < 2097152;
            if (!xz) {
                $rootScope.alert("Terjadi Kesalahan", "Ukuran gambar tidak boleh lebih dari 2MB", "error");
            }
            return xz;
        }
    });
    $scope.gambar = [];
    //    uploader.onSuccessItem = function (fileItem, response) {
    //        if (response.answer == 'File transfer completed') {
    //            var d = new Date();
    //            $scope.gambar.unshift({img: response.img, id: response.id});
    //            $scope.urlgambar = "api/file/jurnal-umum/"+d.getFullYear()+"/"+(d.getMonth()+1)+"/";
    //        }
    //    };
    uploader.onBeforeUploadItem = function(item) {
        item.formData.push({
            id: $scope.form.id,
        });
    };
    $scope.removeFoto = function(paramindex, namaFoto, pid) {
        Data.post('acc/t_jurnal_umum/removegambar', {
            id: pid,
            img: namaFoto
        }).then(function(data) {
            $scope.gambar.splice(paramindex, 1);
        });
    };
    $scope.gambarzoom = function(img) {
        var modalInstance = $uibModal.open({
            template: '<center><img src=' + $scope.urlfoto + img + ' class="img-fluid" ></center>',
            size: 'md',
        });
    };
    $scope.listgambar = function(id) {
        Data.get('acc/t_jurnal_umum/listgambar/' + id).then(function(data) {
            $scope.gambar = data.data.model;
        });
    };
    /* sampe di sini*/
    /*
     * ambil detail
     */
    $scope.getDetail = function(id) {
        var data = {
            id: id
        }
        Data.get(control_link + '/getDetail', data).then(function(data) {
            $scope.listDetail = data.data.list;
        });
    }
    /*
     * tambah detail
     */
    $scope.addDetail = function(val) {
        var comArr = $(".tabletr").last().index() + 1
        var newDet = {
            m_akun_id: {
                id: $scope.akunAll[0].id,
                kode: $scope.akunAll[0].kode,
                nama: $scope.akunAll[0].nama
            },
            m_lokasi_id: val[0].m_lokasi_id,
            keterangan: '',
            kredit: 0,
            debit: 0,
            is_label: false,
        };
        val.splice(comArr, 0, newDet);
        $scope.sumTotal();
    };
    /**
     * Cari kontak
     */
    $scope.cariKontak = function(cari) {
        if (cari.toString().length > 2) {
            Data.get('acc/m_kontak/getKontak', {
                nama: cari
            }).then(function(response) {
                $scope.listKontak = response.data.list;
            });
        }
    };
    /*
     * hapus detail
     */
    $scope.removeDetail = function(val, paramindex) {
        var comArr = eval(val);
        if (comArr.length > 1) {
            val.splice(paramindex, 1);
            $scope.sumTotal();
        } else {
            alert("Something gone wrong");
        }
    };
    /*
     * kalkulasi total detail
     */
    $scope.sumTotal = function() {
        var totalkredit = 0;
        var totaldebit = 0;
        angular.forEach($scope.listDetail, function(value, key) {
            totalkredit += (value.kredit != null) ? parseInt(value.kredit) : 0;
            totaldebit += (value.debit != null) ? parseInt(value.debit) : 0;
        });
        $scope.form.total_debit = totaldebit;
        $scope.form.total_kredit = totalkredit;
    };
    $scope.callServer = function callServer(tableState) {
        tableStateRef = tableState;
        $scope.isLoading = true;
        var offset = tableState.pagination.start || 0;
        var limit = tableState.pagination.number || 20;
        /** set offset and limit */
        var param = {
            offset: offset,
            limit: limit
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
            tableState.pagination.numberOfPages = Math.ceil(response.data.totalItems / limit);
        });
        $scope.isLoading = false;
    };
    /** create */
    $scope.create = function() {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_create = true;
        $scope.is_disable = false;
        $scope.formtitle = "Jurnal Umum | Form Tambah Data";
        $scope.form = {};
        if ($scope.listLokasi.length > 0) $scope.form.m_lokasi_id = $scope.listLokasi[0];
        $scope.form.tanggal = new Date($scope.tanggal_setting);
        if (new Date() >= new Date($scope.tanggal_setting)) {
            $scope.form.tanggal = new Date();
        }
        $scope.listDetail = [{
            m_akun_id: {
                id: $scope.akunAll[0].id,
                kode: $scope.akunAll[0].kode,
                nama: $scope.akunAll[0].nama
            },
            m_lokasi_id: {
                id: $scope.listLokasi[0].id,
                nama: $scope.listLokasi[0].nama
            },
            kredit: 0,
            debit: 0
        }];
        $scope.sumTotal();
    };
    /** update */
    $scope.update = function(form) {
        $scope.is_edit = true;
        $scope.is_view = false;
        $scope.is_update = true;
        $scope.is_disable = true;
        $scope.formtitle = "Jurnal Umum | Edit Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal2);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() + "/" + (parseInt($scope.tanggal_foto.getMonth()) + 1) + "/";
    };
    /** view */
    $scope.view = function(form) {
        $scope.is_edit = true;
        $scope.is_view = true;
        $scope.is_disable = true;
        $scope.formtitle = "Jurnal Umum | Lihat Data : " + form.no_transaksi;
        $scope.form = form;
        $scope.form.tanggal = new Date(form.tanggal2);
        $scope.tanggal_foto = new Date(form.tanggal);
        $scope.getDetail(form.id);
        $scope.listgambar(form.id);
        $scope.urlfoto += $scope.tanggal_foto.getFullYear() + "/" + (parseInt($scope.tanggal_foto.getMonth()) + 1) + "/";
    };
    /** save action */
    $scope.save = function(form, type_save) {
        form["status"] = type_save;
        var data = {
            form: form,
            detail: $scope.listDetail,
        }
        Data.post(control_link + '/save', data).then(function(result) {
            if (result.status_code == 200) {
                $scope.uploadGambar();
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.cancel();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    };
    /** cancel action */
    $scope.cancel = function() {
        if (!$scope.is_view) {
            $scope.callServer(tableStateRef);
        }
        $scope.is_edit = false;
        $scope.is_view = false;
        $scope.urlfoto = "api/file/jurnal-umum/";
    };
    $scope.lokasi = function(select) {
        angular.forEach($scope.listDetail, function(val, key) {
            val.m_lokasi_id = {
                id: select.id,
                nama: select.nama
            }
        });
    };
    /*
     * delete action
     */
    $scope.delete = function(row) {
        var data = angular.copy(row);
        Swal.fire({
            title: "Peringatan ! ",
            text: "Apakah Anda Yakin Ingin Menghapus Permanen Data Ini",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#DD6B55",
            confirmButtonText: "Iya, di Hapus",
            cancelButtonText: "Tidak",
        }).then((result) => {
            if (result.value) {
                row.is_deleted = 1;
                Data.post(control_link + '/delete', row).then(function(result) {
                    $rootScope.alert("Berhasil", "Data berhasil dihapus", "success");
                    $scope.cancel();
                });
            }
        });
    };
    $scope.print = function(row) {
        var data = angular.copy(row);
        window.open("api/acc/t_jurnal_umum/print?" + $.param(row), "_blank");
    };
    /**
     * Modal setting template print
     */
    $scope.modalSetting = function() {
        var modalInstance = $uibModal.open({
            templateUrl: "api/acc/landaacc/tpl/t_jurnal_umum/modal.html",
            controller: "settingPrintCtrl",
            size: "xl",
            backdrop: "static",
            keyboard: false,
        });
        modalInstance.result.then(function(response) {
            if (response.data == undefined) {} else {}
        });
    }
});
app.controller("settingPrintCtrl", function($state, $scope, Data, $uibModalInstance, $rootScope) {
    $scope.templateDefault = "";
    Data.get("acc/t_jurnal_umum/getTemplate").then(function(response) {
        $scope.templateDefault = response.data;
    });
    $scope.close = function() {
        $uibModalInstance.close({
            'data': undefined
        });
    };
    $scope.save = function() {
        var ckeditor_data = CKEDITOR.instances.editor1.getData();
        var params = {
            print_jurnal: ckeditor_data
        };
        Data.post("acc/t_jurnal_umum/saveTemplate", params).then(function(result) {
            if (result.status_code == 200) {
                $rootScope.alert("Berhasil", "Data berhasil disimpan", "success");
                $scope.close();
            } else {
                $rootScope.alert("Terjadi Kesalahan", setErrorMessage(result.errors), "error");
            }
        });
    }
});