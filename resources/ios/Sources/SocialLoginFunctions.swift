import AuthenticationServices
import Foundation
import UIKit

/// System browser sign-in for franciscodlxdev/nativephp-social-login.
/// `OpenAuthSession` returns right away; the outcome reaches PHP as an event.
enum SocialLoginFunctions {
    static let completedEvent = "Franciscodlxdev\\SocialLogin\\Events\\AuthSessionCompleted"
    static let failedEvent = "Franciscodlxdev\\SocialLogin\\Events\\AuthSessionFailed"

    class OpenAuthSession: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            // The PHP layer already restricts plain http to loopback hosts.
            guard let url = parameters["url"] as? String, let authURL = URL(string: url),
                  ["https", "http"].contains(authURL.scheme?.lowercased() ?? ""),
                  let callbackUri = parameters["callbackUri"] as? String,
                  let redirect = URLComponents(string: callbackUri),
                  let scheme = redirect.scheme?.lowercased(), !scheme.isEmpty
            else {
                return BridgeResponse.error(
                    code: "INVALID_CONFIG",
                    message: "The authorization URL must be http(s) and the callback URI absolute"
                )
            }
            if scheme == "https" {
                guard #available(iOS 17.4, *), redirect.host != nil else {
                    return BridgeResponse.error(
                        code: "NOT_SUPPORTED",
                        message: "https redirect URIs need iOS 17.4 or later; use a custom scheme"
                    )
                }
            }
            let ephemeral = parameters["prefersEphemeralSession"] as? Bool ?? false

            // PHP calls in on its own thread; the session lives on the main one.
            DispatchQueue.main.async {
                AuthSessionHolder.shared.start(
                    url: authURL,
                    redirect: redirect,
                    scheme: scheme,
                    ephemeral: ephemeral
                )
            }
            return BridgeResponse.success(data: ["started": true])
        }
    }

    class CancelAuthSession: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            DispatchQueue.main.async {
                // The completion handler reports CANCELLED.
                AuthSessionHolder.shared.cancel()
            }
            return BridgeResponse.success(data: [:])
        }
    }
}

/// Keeps the one ASWebAuthenticationSession alive while it is presented.
final class AuthSessionHolder: NSObject, ASWebAuthenticationPresentationContextProviding {
    static let shared = AuthSessionHolder()

    private var session: ASWebAuthenticationSession?

    func start(url: URL, redirect: URLComponents, scheme: String, ephemeral: Bool) {
        // A new sign-in replaces an abandoned one; PHP already dropped its state.
        session?.cancel()
        session = nil

        let completion: ASWebAuthenticationSession.CompletionHandler = { [weak self] callbackURL, error in
            self?.session = nil
            if let callbackURL {
                Self.send(SocialLoginFunctions.completedEvent, ["url": callbackURL.absoluteString])
                return
            }
            let nsError = error as NSError?
            let code: String
            let message: String
            if nsError?.domain == ASWebAuthenticationSessionErrorDomain,
               nsError?.code == ASWebAuthenticationSessionError.canceledLogin.rawValue {
                code = "CANCELLED"
                message = "The user closed the browser"
            } else if nsError?.domain == ASWebAuthenticationSessionErrorDomain {
                code = "NOT_SUPPORTED"
                message = nsError?.localizedDescription ?? "The sign-in could not be presented"
            } else {
                code = "PROVIDER_ERROR"
                message = nsError?.localizedDescription ?? "The sign-in failed"
            }
            Self.send(SocialLoginFunctions.failedEvent, ["code": code, "message": message])
        }

        let created: ASWebAuthenticationSession
        if scheme == "https", #available(iOS 17.4, *), let host = redirect.host {
            created = ASWebAuthenticationSession(
                url: url,
                callback: .https(host: host, path: redirect.path.isEmpty ? "/" : redirect.path),
                completionHandler: completion
            )
        } else {
            created = ASWebAuthenticationSession(url: url, callbackURLScheme: scheme, completionHandler: completion)
        }
        created.presentationContextProvider = self
        created.prefersEphemeralWebBrowserSession = ephemeral
        session = created
        if !created.start() {
            session = nil
            Self.send(SocialLoginFunctions.failedEvent, [
                "code": "NOT_SUPPORTED",
                "message": "The sign-in could not be presented",
            ])
        }
    }

    func cancel() {
        session?.cancel()
    }

    func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        let scenes = UIApplication.shared.connectedScenes.compactMap { $0 as? UIWindowScene }
        let active = scenes.first { $0.activationState == .foregroundActive } ?? scenes.first
        if let window = active?.windows.first(where: { $0.isKeyWindow }) ?? active?.windows.first {
            return window
        }
        if let active {
            return ASPresentationAnchor(windowScene: active)
        }
        return ASPresentationAnchor()
    }

    private static func send(_ event: String, _ payload: [String: Any?]) {
        DispatchQueue.main.async {
            LaravelBridge.shared.send?(event, payload)
        }
    }
}
