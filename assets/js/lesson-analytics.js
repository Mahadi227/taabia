/**
 * Lesson Analytics Tracking JavaScript
 * Tracks student interactions with lessons for analytics
 */

class LessonAnalytics {
    constructor(lessonId, studentId) {
        this.lessonId = lessonId;
        this.studentId = studentId;
        this.startTime = Date.now();
        this.lastActivity = Date.now();
        this.sessionDuration = 0;
        this.isTracking = false;
        this.progressInterval = null;
        this.activityTimeout = null;
        
        // Initialize tracking
        this.init();
    }

    init() {
        if (!this.lessonId || !this.studentId) {
            console.warn('LessonAnalytics: Missing lessonId or studentId');
            return;
        }

        this.startTracking();
        this.setupEventListeners();
        this.trackView();
    }

    startTracking() {
        this.isTracking = true;
        
        // Track progress every 30 seconds
        this.progressInterval = setInterval(() => {
            this.updateProgress();
        }, 30000);

        // Track activity timeout (5 minutes of inactivity)
        this.resetActivityTimeout();
    }

    stopTracking() {
        this.isTracking = false;
        
        if (this.progressInterval) {
            clearInterval(this.progressInterval);
            this.progressInterval = null;
        }
        
        if (this.activityTimeout) {
            clearTimeout(this.activityTimeout);
            this.activityTimeout = null;
        }
    }

    setupEventListeners() {
        // Track user activity
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.resetActivityTimeout();
            }, { passive: true });
        });

        // Track page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseTracking();
            } else {
                this.resumeTracking();
            }
        });

        // Track before page unload
        window.addEventListener('beforeunload', () => {
            this.trackSessionEnd();
        });

        // Track video interactions if video element exists
        const video = document.querySelector('video');
        if (video) {
            this.setupVideoTracking(video);
        }

        // Track form submissions
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', () => {
                this.trackInteraction('form_submit');
            });
        });
    }

    setupVideoTracking(video) {
        video.addEventListener('play', () => {
            this.trackInteraction('video_play');
        });

        video.addEventListener('pause', () => {
            this.trackInteraction('video_pause');
        });

        video.addEventListener('seeked', (e) => {
            this.trackInteraction('video_seek', {
                currentTime: e.target.currentTime,
                duration: e.target.duration
            });
        });

        video.addEventListener('ended', () => {
            this.trackInteraction('video_complete');
        });

        video.addEventListener('timeupdate', () => {
            // Track progress every 10% of video
            const progress = Math.floor((video.currentTime / video.duration) * 10) * 10;
            if (progress > 0 && progress % 10 === 0) {
                this.trackVideoProgress(progress);
            }
        });
    }

    resetActivityTimeout() {
        if (this.activityTimeout) {
            clearTimeout(this.activityTimeout);
        }
        
        this.activityTimeout = setTimeout(() => {
            this.pauseTracking();
        }, 5 * 60 * 1000); // 5 minutes
    }

    pauseTracking() {
        if (this.isTracking) {
            this.sessionDuration += Date.now() - this.lastActivity;
            this.isTracking = false;
        }
    }

    resumeTracking() {
        if (!this.isTracking) {
            this.lastActivity = Date.now();
            this.isTracking = true;
        }
    }

    trackView() {
        this.sendAnalytics('view', {
            session_duration: 0,
            timestamp: new Date().toISOString()
        });
    }

    trackCompletion() {
        this.trackSessionEnd();
        this.sendAnalytics('completion', {
            completion_time: this.sessionDuration,
            timestamp: new Date().toISOString()
        });
    }

    trackRating(rating, review = null) {
        this.sendAnalytics('rating', {
            rating: rating,
            review: review,
            timestamp: new Date().toISOString()
        });
    }

    trackInteraction(type, data = {}) {
        this.sendAnalytics('interaction', {
            interaction_type: type,
            interaction_data: data,
            timestamp: new Date().toISOString()
        });
    }

    trackVideoProgress(progress) {
        this.sendAnalytics('video_progress', {
            progress_percent: progress,
            timestamp: new Date().toISOString()
        });
    }

    updateProgress() {
        if (this.isTracking) {
            this.sessionDuration += Date.now() - this.lastActivity;
            this.lastActivity = Date.now();
            
            this.sendAnalytics('progress', {
                progress_percent: this.calculateProgress(),
                time_spent: Math.floor(this.sessionDuration / 1000),
                timestamp: new Date().toISOString()
            });
        }
    }

    trackSessionEnd() {
        if (this.isTracking) {
            this.sessionDuration += Date.now() - this.lastActivity;
            this.sendAnalytics('session_end', {
                session_duration: Math.floor(this.sessionDuration / 1000),
                timestamp: new Date().toISOString()
            });
        }
    }

    calculateProgress() {
        // Calculate progress based on various factors
        let progress = 0;
        
        // Check if there's a video
        const video = document.querySelector('video');
        if (video && video.duration > 0) {
            progress = Math.floor((video.currentTime / video.duration) * 100);
        }
        
        // Check if there are form fields completed
        const inputs = document.querySelectorAll('input, textarea, select');
        const completedInputs = Array.from(inputs).filter(input => 
            input.value && input.value.trim() !== ''
        );
        if (inputs.length > 0) {
            const formProgress = Math.floor((completedInputs.length / inputs.length) * 100);
            progress = Math.max(progress, formProgress);
        }
        
        return Math.min(progress, 100);
    }

    sendAnalytics(action, data) {
        const payload = {
            lesson_id: this.lessonId,
            student_id: this.studentId,
            action: action,
            data: data
        };

        // Send analytics data
        fetch('api/track_lesson_analytics.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload)
        }).catch(error => {
            console.warn('Analytics tracking failed:', error);
        });
    }
}

// Auto-initialize if lesson data is available
document.addEventListener('DOMContentLoaded', function() {
    // Get lesson and student data from page
    const lessonId = document.querySelector('meta[name="lesson-id"]')?.content;
    const studentId = document.querySelector('meta[name="student-id"]')?.content;
    
    if (lessonId && studentId) {
        window.lessonAnalytics = new LessonAnalytics(lessonId, studentId);
    }
});

// Export for manual initialization
window.LessonAnalytics = LessonAnalytics;
