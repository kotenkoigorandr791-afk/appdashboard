package orbit.nex.gitea.activities;

import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Handler;
import android.os.Looper;
import android.text.InputType;
import android.util.TypedValue;
import android.view.Gravity;
import android.view.View;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.ProgressBar;
import android.widget.ScrollView;
import android.widget.TextView;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.card.MaterialCardView;
import com.google.android.material.color.MaterialColors;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;
import java.net.HttpURLConnection;
import java.net.URI;
import java.net.URL;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import org.json.JSONObject;
import orbit.nex.gitea.R;

/**
 * Sign-in and on-boarding surface, laid out like the Spends app: a single rounded card on the
 * themed background, a rounded brand mark, a swipeable feature carousel and soft 20dp fields.
 */
final class NexAuthView {
    interface Listener {
        void onAuthorized(String origin, String token);
    }

    private final LoginActivity activity;
    private final Listener listener;
    private final SharedPreferences prefs;
    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private final Handler main = new Handler(Looper.getMainLooper());
    private final String device;
    private LinearLayout body;
    private TextView error;
    private String origin = "";
    private String domain;
    private boolean passwordVisible;
    private boolean busy;
    private volatile boolean cancelled;
    private int page;
    private final String[] features = {
        "Repositories in your pocket",
        "Track issues and pull requests",
        "Stay up to date with notifications",
        "Collaborate with your team"
    };
    private final int[] featureIcons = {
        R.drawable.ic_repo,
        R.drawable.ic_pull_request,
        R.drawable.ic_notifications,
        R.drawable.ic_organization
    };

    NexAuthView(LoginActivity activity, Listener listener) {
        this.activity = activity;
        this.listener = listener;
        prefs = activity.getSharedPreferences("nex_auth", Context.MODE_PRIVATE);
        domain = prefs.getString("domain", "");
        String id = prefs.getString("device_id", "");
        if (id.isEmpty()) {
            byte[] bytes = new byte[32];
            new SecureRandom().nextBytes(bytes);
            StringBuilder hex = new StringBuilder();
            for (byte b : bytes) hex.append(String.format(Locale.ROOT, "%02x", b & 255));
            id = hex.toString();
            prefs.edit().putString("device_id", id).apply();
        }
        device = id;
    }

    int dp(float value) {
        return (int) (value * activity.getResources().getDisplayMetrics().density + .5f);
    }

    int color(int attr) {
        return MaterialColors.getColor(activity, attr, Color.GRAY);
    }

    TextView text(String value, int size) {
        TextView view = new TextView(activity);
        view.setText(value);
        view.setTextSize(size);
        view.setGravity(Gravity.CENTER);
        view.setTextColor(color(com.google.android.material.R.attr.colorOnSurface));
        return view;
    }

    void add(View view) {
        add(view, 16, -1, -2);
    }

    private void add(View view, int topMarginDp, int width, int height) {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(width, height);
        p.topMargin = dp(topMarginDp);
        body.addView(view, p);
    }

    private void addCentered(View view, int topMarginDp) {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-2, -2);
        p.topMargin = dp(topMarginDp);
        p.gravity = Gravity.CENTER_HORIZONTAL;
        body.addView(view, p);
    }

    private MaterialCardView card(int radiusDp, int backgroundColor) {
        MaterialCardView card = new MaterialCardView(activity);
        card.setRadius(dp(radiusDp));
        card.setCardElevation(0);
        card.setStrokeWidth(0);
        card.setCardBackgroundColor(backgroundColor);
        return card;
    }

    private MaterialButton button(String title, boolean outlined, Runnable action) {
        MaterialButton b =
                outlined
                        ? new MaterialButton(
                                activity,
                                null,
                                com.google.android.material.R.attr.materialButtonOutlinedStyle)
                        : new MaterialButton(activity);
        b.setText(title);
        b.setAllCaps(false);
        b.setEnabled(!busy);
        b.setCornerRadius(dp(20));
        b.setInsetTop(0);
        b.setInsetBottom(0);
        b.setOnClickListener(v -> action.run());
        return b;
    }

    TextInputEditText field(String hint, String placeholder, int inputType, String value) {
        TextInputLayout layout =
                new TextInputLayout(
                        activity, null, com.google.android.material.R.attr.textInputOutlinedStyle);
        layout.setHint(hint);
        layout.setBoxCornerRadii(dp(20), dp(20), dp(20), dp(20));
        TextInputEditText edit = new TextInputEditText(activity);
        edit.setSingleLine(true);
        edit.setInputType(inputType);
        edit.setText(value);
        layout.addView(edit);
        add(layout);
        return edit;
    }

    View render() {
        ScrollView scroll = new ScrollView(activity);
        scroll.setFillViewport(true);
        scroll.setBackgroundColor(color(android.R.attr.colorBackground));

        LinearLayout container = new LinearLayout(activity);
        container.setOrientation(LinearLayout.VERTICAL);
        container.setGravity(Gravity.CENTER_HORIZONTAL);
        container.setPadding(dp(20), dp(20), dp(20), dp(20));
        scroll.addView(container, new ScrollView.LayoutParams(-1, -2));

        MaterialCardView card =
                card(28, color(com.google.android.material.R.attr.colorSurfaceContainerHighest));
        container.addView(card, new LinearLayout.LayoutParams(-1, -2));

        body = new LinearLayout(activity);
        body.setOrientation(LinearLayout.VERTICAL);
        body.setGravity(Gravity.CENTER_HORIZONTAL);
        body.setPadding(dp(20), dp(20), dp(20), dp(20));
        card.addView(body);

        MaterialCardView logoCard = card(18, activity.getColor(R.color.nex_dark_bg_primary));
        ImageView logo = new ImageView(activity);
        logo.setImageResource(R.drawable.app_logo);
        logo.setScaleType(ImageView.ScaleType.FIT_CENTER);
        logoCard.addView(
                logo, new FrameLayout.LayoutParams(-1, -1, Gravity.CENTER));
        LinearLayout.LayoutParams logoParams = new LinearLayout.LayoutParams(dp(72), dp(72));
        body.addView(logoCard, logoParams);

        TextView title = text("NexOrbit", 24);
        title.setTypeface(null, Typeface.BOLD);
        title.setTextColor(color(com.google.android.material.R.attr.colorOnSurface));
        addCentered(title, 16);

        if (origin.isEmpty()) {
            add(featurePage(), 4, -1, dp(125));
            addCentered(dots(), 4);

            TextInputEditText input =
                    field(
                            "Server domain",
                            "nxorbit.top",
                            InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_URI,
                            domain);

            MaterialButton continueButton =
                    button(
                            "Continue",
                            false,
                            () -> {
                                domain = input.getText().toString();
                                String validated = normalize(domain);
                                if (validated == null) {
                                    error.setText("Invalid domain");
                                    return;
                                }
                                prefs.edit().putString("domain", domain).apply();
                                origin = validated;
                                show();
                            });
            add(continueButton, 16, -1, dp(52));
        } else {
            TextView server = text(origin, 12);
            server.setTextColor(color(com.google.android.material.R.attr.colorOnSurfaceVariant));
            addCentered(server, 12);

            MaterialButton nexOrbit =
                    button("Sign in with NexOrbit", false, this::browserLogin);
            add(nexOrbit, 16, -1, dp(52));

            MaterialButton emailButton =
                    button(
                            "Sign in with email and password",
                            true,
                            () -> {
                                passwordVisible = !passwordVisible;
                                show();
                            });
            add(emailButton, 12, -1, dp(52));

            if (passwordVisible) {
                TextInputEditText email =
                        field(
                                "Email",
                                "you@example.com",
                                InputType.TYPE_CLASS_TEXT
                                        | InputType.TYPE_TEXT_VARIATION_EMAIL_ADDRESS,
                                "");
                TextInputEditText password =
                        field(
                                "Password",
                                null,
                                InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_VARIATION_PASSWORD,
                                "");
                MaterialButton signIn =
                        button(
                                "Sign in",
                                false,
                                () ->
                                        passwordLogin(
                                                email.getText().toString().trim(),
                                                password.getText().toString()));
                add(signIn, 16, -1, dp(52));
            }

            MaterialButton change =
                    button(
                            "Change domain",
                            true,
                            () -> {
                                origin = "";
                                passwordVisible = false;
                                show();
                            });
            add(change, 12, -1, dp(52));

            if (busy) {
                ProgressBar progress = new ProgressBar(activity);
                addCentered(progress, 16);
            }
        }

        error = text("", 14);
        error.setTextColor(color(androidx.appcompat.R.attr.colorError));
        addCentered(error, 12);

        TextView footer = text("Powered by NexOrbit", 12);
        footer.setTextColor(color(com.google.android.material.R.attr.colorOnSurfaceVariant));
        container.addView(footer, footerParams());

        return scroll;
    }

    private LinearLayout.LayoutParams footerParams() {
        LinearLayout.LayoutParams p = new LinearLayout.LayoutParams(-2, -2);
        p.topMargin = dp(16);
        p.gravity = Gravity.CENTER_HORIZONTAL;
        return p;
    }

    private LinearLayout featurePage() {
        LinearLayout pageLayout = new LinearLayout(activity);
        pageLayout.setOrientation(LinearLayout.VERTICAL);
        pageLayout.setGravity(Gravity.CENTER);
        pageLayout.setMinimumHeight(dp(125));
        pageLayout.setOnClickListener(v -> {
            page = (page + 1) % features.length;
            show();
        });

        MaterialCardView iconCircle = card(99, color(com.google.android.material.R.attr.colorPrimaryContainer));
        ImageView icon = new ImageView(activity);
        icon.setImageResource(featureIcons[page]);
        icon.setColorFilter(color(com.google.android.material.R.attr.colorOnPrimaryContainer));
        icon.setScaleType(ImageView.ScaleType.FIT_CENTER);
        iconCircle.addView(icon, new FrameLayout.LayoutParams(-1, -1, Gravity.CENTER));
        LinearLayout.LayoutParams circleParams = new LinearLayout.LayoutParams(dp(64), dp(64));
        pageLayout.addView(iconCircle, circleParams);

        TextView feature = text(features[page], 14);
        feature.setTextColor(color(com.google.android.material.R.attr.colorOnSurfaceVariant));
        feature.setMaxLines(2);
        feature.setEllipsize(null);
        LinearLayout.LayoutParams featureParams = new LinearLayout.LayoutParams(-1, -2);
        featureParams.topMargin = dp(10);
        pageLayout.addView(feature, featureParams);
        return pageLayout;
    }

    private LinearLayout dots() {
        LinearLayout row = new LinearLayout(activity);
        row.setGravity(Gravity.CENTER);
        row.setOrientation(LinearLayout.HORIZONTAL);
        for (int i = 0; i < features.length; i++) {
            final int index = i;
            View dot = new View(activity);
            GradientDrawable shape = new GradientDrawable();
            shape.setShape(GradientDrawable.RECTANGLE);
            shape.setCornerRadius(dp(99));
            shape.setColor(
                    i == page
                            ? color(androidx.appcompat.R.attr.colorPrimary)
                            : color(com.google.android.material.R.attr.colorOutlineVariant));
            dot.setBackground(shape);
            LinearLayout.LayoutParams p =
                    new LinearLayout.LayoutParams(i == page ? dp(22) : dp(8), dp(8));
            p.setMarginEnd(dp(8));
            dot.setOnClickListener(v -> {
                page = index;
                show();
            });
            row.addView(dot, p);
        }
        return row;
    }

    void show() {
        activity.setContentView(render());
    }

    static String normalize(String value) {
        if (value.isEmpty() || !value.equals(value.trim()) || value.matches(".*\\s.*")) return null;
        try {
            URI uri = new URI(value.contains("://") ? value : "https://" + value);
            String scheme = uri.getScheme().toLowerCase(Locale.ROOT);
            if ((!scheme.equals("http") && !scheme.equals("https"))
                    || uri.getHost() == null
                    || uri.getRawUserInfo() != null
                    || uri.getRawQuery() != null
                    || uri.getRawFragment() != null
                    || !(uri.getRawPath() == null
                            || uri.getRawPath().isEmpty()
                            || uri.getRawPath().equals("/"))
                    || uri.getPort() == 0
                    || uri.getPort() > 65535
                    || uri.getRawAuthority().endsWith(":")) return null;
            return scheme + "://" + uri.getRawAuthority();
        } catch (Exception e) {
            return null;
        }
    }

    JSONObject request(String path, JSONObject body, String token) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(origin + path).openConnection();
        try {
            c.setRequestMethod(body == null ? "GET" : "POST");
            c.setConnectTimeout(15000);
            c.setReadTimeout(15000);
            c.setInstanceFollowRedirects(false);
            c.setRequestProperty("Accept", "application/json");
            c.setRequestProperty("X-Device-Id", device);
            if (token != null) c.setRequestProperty("Authorization", "Bearer " + token);
            if (body != null) {
                c.setDoOutput(true);
                c.setRequestProperty("Content-Type", "application/json");
                try (java.io.OutputStream out = c.getOutputStream()) {
                    out.write(body.toString().getBytes(StandardCharsets.UTF_8));
                }
            }
            int status = c.getResponseCode();
            java.io.InputStream stream =
                    status >= 200 && status < 300 ? c.getInputStream() : c.getErrorStream();
            if (stream == null) throw new IllegalStateException("server_error");
            String response;
            try (java.util.Scanner scanner =
                    new java.util.Scanner(stream, "UTF-8").useDelimiter("\\A")) {
                response = scanner.hasNext() ? scanner.next() : "";
            }
            JSONObject result;
            try {
                result = new JSONObject(response);
            } catch (Exception e) {
                throw new IllegalStateException("server_error");
            }
            if (status < 200 || status >= 300 || !result.optBoolean("ok"))
                throw new IllegalStateException(result.optString("error", "server_error"));
            return result;
        } finally {
            c.disconnect();
        }
    }

    JSONObject payload() throws Exception {
        return new JSONObject().put("platform", "nexorbit").put("device_name", "NexOrbit");
    }

    void passwordLogin(String email, String password) {
        if (email.isEmpty() || password.isEmpty()) {
            error.setText("Enter your email and password");
            return;
        }
        start(
                () ->
                        connect(
                                request(
                                                "/public-api/auth/login",
                                                payload()
                                                        .put("email", email)
                                                        .put("password", password),
                                                null)
                                        .getString("token")));
    }

    void browserLogin() {
        start(
                () -> {
                    JSONObject req =
                            request("/public-api/auth/client_auth/request", payload(), null);
                    URI browser = new URI(req.getString("browser_url"));
                    URI trusted = new URI(origin);
                    URI target =
                            new URI(
                                    trusted.getScheme(),
                                    trusted.getRawAuthority(),
                                    browser.getRawPath(),
                                    browser.getRawQuery(),
                                    null);
                    main.post(
                            () ->
                                    activity.startActivity(
                                            new Intent(
                                                    Intent.ACTION_VIEW, Uri.parse(target.toString()))));
                    long deadline = android.os.SystemClock.elapsedRealtime() + 600000;
                    while (!cancelled && android.os.SystemClock.elapsedRealtime() < deadline) {
                        Thread.sleep(2000);
                        JSONObject result =
                                request(
                                        "/public-api/auth/client_auth/poll",
                                        new JSONObject()
                                                .put("request_id", req.getString("request_id"))
                                                .put("poll_token", req.getString("poll_token")),
                                        null);
                        if (result.optString("status").equals("approved")) {
                            connect(result.getString("token"));
                            return;
                        }
                        if (result.optString("status").equals("expired"))
                            throw new IllegalStateException("timeout");
                    }
                    throw new IllegalStateException("timeout");
                });
    }

    void connect(String token) throws Exception {
        JSONObject result = request("/public-api/gitea/connect", null, token);
        String giteaToken = result.getString("access_token");
        prefs.edit().putString("dashboard_token", token).apply();
        main.post(
                () -> {
                    if (!cancelled) listener.onAuthorized(origin, giteaToken);
                });
    }

    interface Work {
        void run() throws Exception;
    }

    void start(Work work) {
        busy = true;
        show();
        executor.execute(
                () -> {
                    try {
                        work.run();
                    } catch (Exception e) {
                        String key = e instanceof java.io.IOException ? "connection_error" : e.getMessage();
                        String message;
                        switch (key == null ? "" : key) {
                            case "invalid_credentials":
                                message = "Invalid email or password";
                                break;
                            case "email_not_verified":
                                message = "Email is not verified";
                                break;
                            case "device_id_required":
                                message = "Missing device ID";
                                break;
                            case "device_mismatch":
                                message = "Device does not match the authorization request";
                                break;
                            case "already_claimed":
                                message = "Authorization request was already claimed";
                                break;
                            case "timeout":
                                message = "Authorization timed out";
                                break;
                            case "connection_error":
                                message = "Could not connect to the server";
                                break;
                            default:
                                message = "Server error";
                                break;
                        }
                        main.post(
                                () -> {
                                    if (!cancelled) {
                                        busy = false;
                                        show();
                                        error.setText(message);
                                    }
                                });
                    }
                });
    }

    void close() {
        cancelled = true;
        executor.shutdownNow();
    }
}
