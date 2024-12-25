/**
 * File: admin/js/handlers/lus-chart-handler.js
 * Handles chart creation and updates
 */

LUS.Handlers.Charts = {
  init(statsData) {
    if (typeof Chart === "undefined") return;

    this.initOverallChart(statsData.overall);
    this.initScoreDistribution(statsData.passages);
    this.initTimelineChart(statsData.timeStats);
    this.initDifficultyChart(statsData.difficultyStats);
  },

  initOverallChart(stats) {
    const ctx = document.getElementById("overall-stats-chart");
    if (!ctx) return;

    new Chart(ctx, {
      type: "doughnut",
      data: {
        labels: [
          LUS.Strings.recordings,
          LUS.Strings.students,
          LUS.Strings.assessments,
        ],
        datasets: [
          {
            data: [
              stats.total_recordings,
              stats.unique_students,
              stats.total_questions_answered,
            ],
            backgroundColor: ["#4CAF50", "#2196F3", "#FFC107"],
          },
        ],
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: "bottom",
          },
          title: {
            display: true,
            text: LUS.Strings.overallStatistics,
          },
        },
      },
    });
  },

  initScoreDistribution(passageStats) {
    const ctx = document.getElementById("score-distribution-chart");
    if (!ctx) return;

    const datasets = passageStats.map((passage) => ({
      label: passage.title,
      data: this.calculateScoreDistribution(passage.scores),
      borderColor: this.getRandomColor(),
      fill: false,
    }));

    new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["0-20", "21-40", "41-60", "61-80", "81-100"],
        datasets: datasets,
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
        },
        plugins: {
          title: {
            display: true,
            text: LUS.Strings.scoreDistribution,
          },
        },
      },
    });
  },

  initTimelineChart(timeStats) {
    const ctx = document.getElementById("timeline-chart");
    if (!ctx) return;

    new Chart(ctx, {
      type: "line",
      data: {
        labels: timeStats.map((stat) => stat.period),
        datasets: [
          {
            label: LUS.Strings.recordings,
            data: timeStats.map((stat) => stat.recording_count),
            borderColor: "#4CAF50",
            yAxisID: "y-recordings",
          },
          {
            label: LUS.Strings.averageScore,
            data: timeStats.map((stat) => stat.avg_score),
            borderColor: "#2196F3",
            yAxisID: "y-score",
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          "y-recordings": {
            type: "linear",
            position: "left",
            title: {
              display: true,
              text: LUS.Strings.numberOfRecordings,
            },
          },
          "y-score": {
            type: "linear",
            position: "right",
            title: {
              display: true,
              text: LUS.Strings.averageScore,
            },
            min: 0,
            max: 100,
          },
        },
        plugins: {
          title: {
            display: true,
            text: LUS.Strings.progressOverTime,
          },
        },
      },
    });
  },

  initDifficultyChart(difficultyStats) {
    const ctx = document.getElementById("difficulty-chart");
    if (!ctx) return;

    new Chart(ctx, {
      type: "bubble",
      data: {
        datasets: [
          {
            label: LUS.Strings.difficultyLevels,
            data: difficultyStats.map((stat) => ({
              x: stat.difficulty_level,
              y: stat.avg_score,
              r: Math.sqrt(stat.recording_count) * 5, // Scale bubble size
            })),
            backgroundColor: this.getColorArray(difficultyStats.length),
          },
        ],
      },
      options: {
        responsive: true,
        scales: {
          x: {
            title: {
              display: true,
              text: LUS.Strings.difficultyLevel,
            },
          },
          y: {
            beginAtZero: true,
            max: 100,
            title: {
              display: true,
              text: LUS.Strings.averageScore,
            },
          },
        },
        plugins: {
          tooltip: {
            callbacks: {
              label: (context) => {
                const data = difficultyStats[context.dataIndex];
                return [
                  `${LUS.Strings.difficultyLevel}: ${data.difficulty_level}`,
                  `${LUS.Strings.averageScore}: ${data.avg_score.toFixed(1)}%`,
                  `${LUS.Strings.recordings}: ${data.recording_count}`,
                ];
              },
            },
          },
        },
      },
    });
  },

  calculateScoreDistribution(scores) {
    const distribution = [0, 0, 0, 0, 0];
    scores.forEach((score) => {
      const index = Math.min(Math.floor(score / 20), 4);
      distribution[index]++;
    });
    return distribution;
  },

  getRandomColor() {
    const letters = "0123456789ABCDEF";
    let color = "#";
    for (let i = 0; i < 6; i++) {
      color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
  },

  getColorArray(length) {
    return Array.from({ length }, () => this.getRandomColor());
  },
};
