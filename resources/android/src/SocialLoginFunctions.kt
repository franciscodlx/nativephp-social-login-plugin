package dev.franciscodlx.nativephp.sociallogin

import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Handler
import android.os.Looper
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.bridge.BridgeResponse
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.lang.ref.WeakReference

/**
 * System browser sign-in for franciscodlxdev/nativephp-social-login.
 * OpenAuthSession returns right away; the outcome reaches PHP as an event.
 */
object SocialLoginFunctions {
  private const val COMPLETED_EVENT = "Franciscodlxdev\\SocialLogin\\Events\\AuthSessionCompleted"
  private const val FAILED_EVENT = "Franciscodlxdev\\SocialLogin\\Events\\AuthSessionFailed"

  class OpenAuthSession(private val activity: FragmentActivity) : BridgeFunction {
    override fun execute(parameters: Map<String, Any>): Map<String, Any> {
      val url = parameters["url"] as? String
      val callbackUri = parameters["callbackUri"] as? String
      val authUri = url?.let(Uri::parse)
      val redirect = callbackUri?.let(Uri::parse)
      // The PHP layer already restricts plain http to loopback hosts.
      if (authUri == null || redirect == null || authUri.scheme !in listOf("https", "http") || redirect.scheme.isNullOrEmpty()) {
        return BridgeResponse.error(AuthSession.INVALID_CONFIG, "The authorization URL must be http(s) and the callback URI absolute")
      }
      if (!handlesRedirect(redirect)) {
        // Without an intent-filter the browser would have nowhere to go back to.
        return BridgeResponse.error(
          AuthSession.INVALID_CONFIG,
          "No intent-filter for \"${redirect.scheme}\": add it to SOCIAL_LOGIN_REDIRECT_SCHEMES in .env and rebuild"
        )
      }

      // A new sign-in replaces an abandoned one; PHP already dropped its state.
      AuthSession.fail(AuthSession.CANCELLED, "Replaced by a new sign-in", notify = false)
      val target = WeakReference(activity)
      val started = AuthSession.begin(redirect, object : AuthSession.Listener {
        override fun onRedirect(url: String) = dispatch(target, COMPLETED_EVENT, JSONObject().put("url", url))
        override fun onError(code: String, message: String) =
          dispatch(target, FAILED_EVENT, JSONObject().put("code", code).put("message", message))
      })
      if (!started) {
        return BridgeResponse.error(AuthSession.INVALID_CONFIG, "Another sign-in is already in progress")
      }

      val ephemeral = parameters["prefersEphemeralSession"] as? Boolean ?: false
      Handler(Looper.getMainLooper()).post {
        try {
          activity.startActivity(AuthSessionActivity.startIntent(activity, url, ephemeral))
        } catch (e: Exception) {
          AuthSession.fail(AuthSession.NOT_SUPPORTED, e.message ?: "Could not start the sign-in")
        }
      }
      return BridgeResponse.success(mapOf("started" to true))
    }

    /** Whether this app's AuthRedirectActivity receives [redirect]. */
    private fun handlesRedirect(redirect: Uri): Boolean {
      val intent = Intent(Intent.ACTION_VIEW, redirect)
        .addCategory(Intent.CATEGORY_BROWSABLE)
        .setPackage(activity.packageName)
      @Suppress("DEPRECATION")
      val matches = activity.packageManager.queryIntentActivities(intent, PackageManager.MATCH_DEFAULT_ONLY)
      return matches.any { it.activityInfo.name == AuthRedirectActivity::class.java.name }
    }
  }

  class CancelAuthSession(private val activity: FragmentActivity) : BridgeFunction {
    override fun execute(parameters: Map<String, Any>): Map<String, Any> {
      if (AuthSession.isActive) {
        Handler(Looper.getMainLooper()).post {
          activity.startActivity(AuthSessionActivity.cancelIntent(activity))
        }
      }
      return BridgeResponse.success(emptyMap())
    }
  }

  private fun dispatch(target: WeakReference<FragmentActivity>, event: String, payload: JSONObject) {
    // Events must be dispatched on the main thread or they are dropped.
    Handler(Looper.getMainLooper()).post {
      target.get()?.let { NativeActionCoordinator.dispatchEvent(it, event, payload.toString()) }
    }
  }
}
