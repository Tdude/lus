/**
 * File: admin/js/handlers/lus-results-handler.js
 */

LUS.Handlers.Results = {
  init() {
    this.bindEvents();
    this.initCharts();
  },

  bindEvents() {
    // Auto-submit on filter change
    $(".lus-results-filters select").on("change", function () {
      $(this).closest("form").submit();
    });

    // Print results
    $("#print-results").on("click", (e) => {
      e.preventDefault();
      window.print();
    });

    // Export results
    $("#export-results").on("click", (e) => {
      e.preventDefault();
      this.handleExport();
    });

    // Refresh stats
    $("#refresh-stats").on("click", (e) => {
      e.preventDefault();
      this.refreshStats();
    });
  },

  // Various charts nice-to-haves
  initCharts() {
    if (typeof Chart === "undefined") return;

    // Initialize all charts
    const charts = {
      "overall-stats-chart": this.initOverallChart,
      "score-distribution-chart": this.initScoreDistributionChart,
      "trend-stats-chart": this.initTrendChart,
      "questions-success-chart": this.initQuestionsSuccessChart,
      "time-per-recording-chart": this.initTimePerRecordingChart,
      "student-progress-chart": this.initStudentProgressChart,
    };

    Object.entries(charts).forEach(([id, initFunction]) => {
      const ctx = document.getElementById(id);
      if (ctx) initFunction.call(this, ctx);
    });
  },

  initScoreDistributionChart(ctx) {
    LUS.Data.request("get_score_distribution", {
      passage_id: ctx.dataset.passageId,
    }).then((response) => {
      new Chart(ctx, {
        type: "bar",
        data: {
          labels: ["0-20", "21-40", "41-60", "61-80", "81-100"],
          datasets: [
            {
              label: LUS.Strings.scoreDistribution,
              data: response.distribution,
              backgroundColor: "#4CAF50",
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: LUS.Strings.numberOfStudents,
              },
            },
            x: {
              title: {
                display: true,
                text: LUS.Strings.scoreRanges,
              },
            },
          },
        },
      });
    });
  },

  initQuestionsSuccessChart(ctx) {
    LUS.Data.request("get_questions_success_rate").then((response) => {
      new Chart(ctx, {
        type: "horizontalBar",
        data: {
          labels: response.questions,
          datasets: [
            {
              label: LUS.Strings.successRate,
              data: response.rates,
              backgroundColor: "#2196F3",
            },
          ],
        },
        options: {
          indexAxis: "y",
          responsive: true,
          scales: {
            x: {
              beginAtZero: true,
              max: 100,
              title: {
                display: true,
                text: LUS.Strings.percentageCorrect,
              },
            },
          },
        },
      });
    });
  },

  initTimePerRecordingChart(ctx) {
    LUS.Data.request("get_recording_times").then((response) => {
      new Chart(ctx, {
        type: "line",
        data: {
          labels: response.dates,
          datasets: [
            {
              label: LUS.Strings.averageTime,
              data: response.times,
              borderColor: "#FFC107",
              fill: false,
            },
          ],
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              title: {
                display: true,
                text: LUS.Strings.seconds,
              },
            },
          },
        },
      });
    });
  },

  initStudentProgressChart(ctx) {
    LUS.Data.request("get_student_progress").then((response) => {
      new Chart(ctx, {
        type: "line",
        data: {
          labels: response.dates,
          datasets: response.students.map((student) => ({
            label: student.name,
            data: student.scores,
            borderColor: student.color,
            fill: false,
          })),
        },
        options: {
          responsive: true,
          scales: {
            y: {
              beginAtZero: true,
              max: 100,
              title: {
                display: true,
                text: LUS.Strings.score,
              },
            },
          },
        },
      });
    });
  },

  initOverallChart() {
    const ctx = document.getElementById("overall-stats-chart");
    if (!ctx) return;

    const data = {
      labels: [
        LUS.Strings.recordings,
        LUS.Strings.students,
        LUS.Strings.assessments,
      ],
      datasets: [
        {
          data: [
            ctx.dataset.recordings,
            ctx.dataset.students,
            ctx.dataset.assessments,
          ],
          backgroundColor: ["#4CAF50", "#2196F3", "#FFC107"],
        },
      ],
    };

    new Chart(ctx, {
      type: "doughnut",
      data: data,
      options: {
        responsive: true,
        maintainAspectRatio: false,
      },
    });
  },

  initTrendChart() {
    const ctx = document.getElementById("trend-stats-chart");
    if (!ctx) return;

    // Show loading state
    LUS.UI.LoadingState.show(ctx, "", {
      spinnerPosition: "replace",
    });

    // Fetch trend data
    LUS.Data.request("get_trend_stats", {
      passage_id: ctx.dataset.passageId,
      date_range: ctx.dataset.dateRange,
    })
      .then((response) => {
        new Chart(ctx, {
          type: "line",
          data: {
            labels: response.dates,
            datasets: [
              {
                label: LUS.Strings.averageScore,
                data: response.scores,
                borderColor: "#4CAF50",
                fill: false,
              },
            ],
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
              },
            },
          },
        });

        LUS.UI.LoadingState.hide(ctx);
      })
      .catch((error) => {
        LUS.UI.LoadingState.error(ctx, error.message);
      });
  },

  refreshStats() {
    const $container = $(".lus-results-container");

    LUS.UI.LoadingState.show($container, LUS.Strings.refreshing);

    // Get current filter values
    const passageId = $('select[name="passage_id"]').val();
    const dateRange = $('select[name="date_range"]').val();

    // Refresh stats
    LUS.Data.request("refresh_stats", {
      passage_id: passageId,
      date_range: dateRange,
    })
      .then(() => {
        location.reload();
      })
      .catch((error) => {
        LUS.UI.LoadingState.error($container, error.message);
      });
  },

  // Add to lus-results-handler.js

  handleExport(format = "csv") {
    const $button = $("#export-results");

    LUS.UI.LoadingState.show($button, LUS.Strings.exporting);

    // Gather chart images if they exist
    const chartPromises = [];
    if (format === "pdf") {
      document.querySelectorAll("canvas").forEach((canvas) => {
        chartPromises.push(
          new Promise((resolve) => {
            resolve({
              id: canvas.id,
              data: canvas.toDataURL("image/png"),
            });
          })
        );
      });
    }

    Promise.all(chartPromises)
      .then((chartData) => {
        return LUS.Data.request("export_results", {
          format: format,
          passage_id: $('select[name="passage_id"]').val(),
          date_range: $('select[name="date_range"]').val(),
          charts: chartData,
        });
      })
      .then((response) => {
        if (format === "json") {
          // Display in new window for JSON
          const win = window.open("", "JSON Export");
          win.document.write(
            "<pre>" + JSON.stringify(response.data, null, 2) + "</pre>"
          );
        } else {
          // Download file for CSV and PDF
          const blob = new Blob([response.data], { type: response.mime_type });
          const url = window.URL.createObjectURL(blob);
          const a = document.createElement("a");
          a.href = url;
          a.download = response.filename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          window.URL.revokeObjectURL(url);
        }

        LUS.UI.LoadingState.success($button, LUS.Strings.exported);
      })
      .catch((error) => {
        LUS.UI.LoadingState.error($button, error.message);
      });
  },
};

// Initialize when document is ready
document.addEventListener("DOMContentLoaded", () => {
  if (document.querySelector(".lus-results-container")) {
    LUS.Handlers.Results.init();
  }
});
