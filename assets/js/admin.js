(function ($) {
  "use strict";

  var LD_Admin = {
    progressModal: null,
    progressBar: null,
    progressText: null,
    currentBatchId: null,
    statusCheckInterval: null,

    init: function () {
      this.createProgressModal();
      this.bindEvents();
    },

    createProgressModal: function () {
      var modalHtml =
        '<div id="ld-progress-modal" class="ld-modal" style="display: none;">' +
        '<div class="ld-modal-content">' +
        '<div class="ld-modal-header">' +
        '<h3 id="ld-progress-title">Processing...</h3>' +
        "</div>" +
        '<div class="ld-modal-body">' +
        '<div class="ld-progress-container">' +
        '<div class="ld-progress-bar">' +
        '<div class="ld-progress-fill" style="width: 0%;"></div>' +
        "</div>" +
        '<div class="ld-progress-text">Starting...</div>' +
        '<div class="ld-progress-details"></div>' +
        "</div>" +
        "</div>" +
        '<div class="ld-modal-footer">' +
        '<button type="button" class="button" id="ld-progress-close" disabled>Close</button>' +
        "</div>" +
        "</div>" +
        "</div>";

      $("body").append(modalHtml);
      this.progressModal = $("#ld-progress-modal");
      this.progressBar = $(".ld-progress-fill");
      this.progressText = $(".ld-progress-text");
    },

    bindEvents: function () {
      var self = this;

      // Import form submission
      $("#ld-import-form").on("submit", function (e) {
        e.preventDefault();
        self.handleImport(this);
      });

      // Export form submission
      $("#ld-export-form").on("submit", function (e) {
        e.preventDefault();
        self.handleExport(this);
      });

      // Progress modal close
      $("#ld-progress-close").on("click", function () {
        self.hideProgressModal();
      });

      // Delete all data button
      $("#ld-delete-all-data").on("click", function (e) {
        e.preventDefault();
        if (
          confirm(
            "Are you sure you want to delete all LearnDash data? This action cannot be undone.",
          )
        ) {
          self.handleDeleteAllData();
        }
      });

      // Clear logs button
      $("#ld-clear-logs").on("click", function (e) {
        e.preventDefault();
        if (confirm("Delete all plugin logs? This cannot be undone.")) {
          $.ajax({
            url: ld_ajax.ajax_url,
            type: "POST",
            data: {
              action: "ld_clear_logs",
              nonce: ld_ajax.clear_logs_nonce,
            },
            success: function (response) {
              if (response.success) {
                location.reload();
              } else {
                alert(
                  "Failed to delete logs: " + (response.data || "Unknown error"),
                );
              }
            },
            error: function (xhr, status, error) {
              alert("Failed to delete logs: " + error);
            },
          });
        }
      });
    },

    handleImport: function (form) {
      var self = this;
      var formData = new FormData(form);
      formData.append("action", "ld_import");

      this.showProgressModal(
        "Import Progress",
        "Uploading and validating file...",
      );

      $.ajax({
        url: ld_ajax.ajax_url,
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function (response) {
          console.log("Import response", response);
          if (response.success) {
            var result = response.data;

            if (result.batch_mode) {
              // Large dataset - monitor batch progress
              self.currentBatchId = result.batch_id;
              self.totalItems = result.total_items;
              self.currentIndex = 0;
              self.updateProgress(
                5,
                "Processing " + result.total_items + " items in background...",
              );
              self.startBatchMonitoring();
              self.processNextItem();
            } else {
              // Small dataset - show progress animation
              self.animateProgressForDirectImport(result);
            }
          } else {
            self.showError(
              "Import failed: " + (response.data || "Unknown error occurred"),
            );
          }
        },
        error: function (xhr, status, error) {
          console.log("Import error", xhr.responseText, status, error);
          self.showError("Import failed: " + error);
        },
      });
    },

    handleExport: function (form) {
      var self = this;
      this.showProgressModal("Export Progress", "Preparing export...");

      // For export, we'll show progress and then redirect to download
      this.updateProgress(50, "Gathering data...");

      setTimeout(function () {
        self.updateProgress(100, "Download starting...");
        form.submit(); // Submit the form normally for file download

        setTimeout(function () {
          self.hideProgressModal();
        }, 2000);
      }, 1000);
    },

    startBatchMonitoring: function () {
      var self = this;

      this.statusCheckInterval = setInterval(function () {
        self.checkBatchStatus();
      }, 2000); // Check every 2 seconds
    },

    checkBatchStatus: function () {
      var self = this;

      $.ajax({
        url: ld_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ld_batch_status",
          batch_id: this.currentBatchId,
          nonce: ld_ajax.batch_status_nonce,
        },
        success: function (response) {
          if (response.success) {
            var data = response.data;
            var progress = Math.round(data.progress);

            self.updateProgress(
              progress,
              "Processing... (" + progress + "% complete)",
            );

            if (data.status === "completed") {
              clearInterval(self.statusCheckInterval);
              self.updateProgress(100, "Import completed successfully!");
              $("#ld-progress-close").prop("disabled", false);

              // Show final results if available
              if (data.batch_data && data.batch_data.data) {
                try {
                  var results = JSON.parse(data.batch_data.data);
                  self.showImportResults(results);
                } catch (e) {
                  console.log("Could not parse batch results", e);
                }
              }
            } else if (data.status === "failed") {
              clearInterval(self.statusCheckInterval);
              self.showError("Import failed during processing");
            }
          } else {
            console.log("Batch status check failed", response);
          }
        },
        error: function (xhr, status, error) {
          console.log("Batch status error", error);
        },
      });
    },

    showProgressModal: function (title, message) {
      $("#ld-progress-title").text(title);
      this.progressText.text(message);
      this.progressBar.css("width", "0%");
      $("#ld-progress-close").prop("disabled", true);
      this.progressModal.show();
    },

    hideProgressModal: function () {
      this.progressModal.hide();
      if (this.statusCheckInterval) {
        clearInterval(this.statusCheckInterval);
        this.statusCheckInterval = null;
      }
      this.currentBatchId = null;
    },

    updateProgress: function (percentage, message) {
      this.progressBar.css("width", percentage + "%");
      this.progressText.text(message);
    },

    showImportResults: function (result) {
      var details = '<div class="ld-results">';
      details += "<p><strong>Import Summary:</strong></p>";
      details += "<p>✅ Imported: " + (result.imported || 0) + " items</p>";
      details += "<p>⏭️ Skipped: " + (result.skipped || 0) + " items</p>";

      if (result.errors && result.errors.length > 0) {
        details += "<p>❌ Errors: " + result.errors.length + " items</p>";
        if (result.errors.length <= 3) {
          details +=
            '<div class="ld-error-list">' +
            result.errors.join("<br>") +
            "</div>";
        } else {
          details +=
            '<div class="ld-error-list">' +
            result.errors.slice(0, 3).join("<br>") +
            "<br><em>... and " +
            (result.errors.length - 3) +
            " more errors</em></div>";
        }
      }

      details += "</div>";
      $(".ld-progress-details").html(details);
    },

    animateProgressForDirectImport: function (result) {
      var self = this;
      var steps = [
        { progress: 20, message: "Validating data..." },
        { progress: 60, message: "Importing courses..." },
        { progress: 80, message: "Importing lessons and topics..." },
        { progress: 100, message: "Import completed!" },
      ];
      var stepIndex = 0;

      function nextStep() {
        if (stepIndex < steps.length) {
          var step = steps[stepIndex];
          self.updateProgress(step.progress, step.message);
          stepIndex++;
          setTimeout(nextStep, 500); // 500ms delay between steps
        } else {
          self.showImportResults(result);
          $("#ld-progress-close").prop("disabled", false);
        }
      }

      nextStep();
    },

    showError: function (message) {
      this.updateProgress(0, "Error occurred");
      $(".ld-progress-details").html(
        '<div class="ld-error">' + message + "</div>",
      );
      $("#ld-progress-close").prop("disabled", false);
    },

    processNextItem: function () {
      var self = this;
      if (this.currentIndex >= this.totalItems) {
        return;
      }
      $.ajax({
        url: ld_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ld_process_import_item",
          batch_id: this.currentBatchId,
          index: this.currentIndex,
          nonce: ld_ajax.process_item_nonce,
        },
        success: function (response) {
          if (response.success) {
            self.currentIndex++;
            self.processNextItem();
          } else {
            self.showError(
              "Process failed: " + (response.data || "Unknown error"),
            );
          }
        },
        error: function (xhr, status, error) {
          self.showError("Process failed: " + error);
        },
      });
    },

    handleDeleteAllData: function () {
      var self = this;
      this.showProgressModal(
        "Deleting LearnDash Data",
        "Starting deletion process...",
      );

      $.ajax({
        url: ld_ajax.ajax_url,
        type: "POST",
        data: {
          action: "ld_delete_all_data",
          nonce: ld_ajax.delete_nonce,
        },
        success: function (response) {
          if (response.success) {
            self.updateProgress(
              100,
              "All LearnDash data has been deleted successfully.",
            );
            $(".ld-progress-details").html(
              '<div class="ld-success">' + response.data.message + "</div>",
            );
            $("#ld-progress-close").prop("disabled", false);
          } else {
            self.showError(
              "Delete failed: " + (response.data || "Unknown error occurred"),
            );
          }
        },
        error: function (xhr, status, error) {
          self.showError("Delete failed: " + error);
        },
      });
    },
  };

  $(document).ready(function () {
    LD_Admin.init();
  });
})(jQuery);
