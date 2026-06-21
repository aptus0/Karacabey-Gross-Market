"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useAuthStore, type AuthUser } from "@/lib/auth-store";
import { apiRequest, extractErrorMessage } from "@/lib/api";
import { notifyCustomerMutation } from "@/app/_components/CustomerSyncBridge";
import { AppLayout } from "@/app/_layouts/AppLayout";
import { LogOut, AlertCircle, CheckCircle, Loader2, Link2 } from "lucide-react";

interface UpdateProfileRequest {
  name?: string;
  email?: string;
  phone?: string;
}

interface OAuthProvider {
  id: string;
  name: string;
  icon: string;
  connected: boolean;
}

export function AccountSettings() {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const user = useAuthStore((state) => state.user);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const applyRemoteUser = useAuthStore((state) => state.applyRemoteUser);

  // Form states
  const [formData, setFormData] = useState({
    name: "",
    email: "",
    phone: "",
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });

  const [oauthProviders, setOauthProviders] = useState<OAuthProvider[]>([
    { id: "google", name: "Google", icon: "google", connected: false },
    { id: "github", name: "GitHub", icon: "github", connected: false },
  ]);

  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<"profile" | "security" | "oauth">(
    "profile"
  );

  // Guard authentication
  useEffect(() => {
    if (!isAuthenticated) {
      router.replace("/auth/login");
    }
  }, [isAuthenticated, router]);

  // Load user data
  useEffect(() => {
    if (user) {
      setFormData((prev) => ({
        ...prev,
        name: user.name || "",
        email: user.email || "",
        phone: user.phone || "",
      }));
    }
  }, [user]);

  // Handle form input changes
  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    setError(null);
    setSuccess(null);
  };

  // Update profile
  const handleUpdateProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;

    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const payload: UpdateProfileRequest = {};
      if (formData.name) payload.name = formData.name;
      if (formData.email) payload.email = formData.email;
      if (formData.phone) payload.phone = formData.phone;

      const updatedUser = await apiRequest<AuthUser>("/api/v1/auth/profile", {
        method: "PUT",
        headers: {
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify(payload),
      });

      applyRemoteUser(updatedUser);
      notifyCustomerMutation("profile_mutated");
      setSuccess("Profil başarıyla güncellendi!");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(
        extractErrorMessage(err, "Bir hata oluştu")
      );
    } finally {
      setLoading(false);
    }
  };

  // Update password
  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!token) return;

    if (formData.newPassword !== formData.confirmPassword) {
      setError("Şifreler eşleşmiyor");
      return;
    }

    if (formData.newPassword.length < 8) {
      setError("Şifre en az 8 karakter olmalıdır");
      return;
    }

    setLoading(true);
    setError(null);
    setSuccess(null);

    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/v1/auth/change-password`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
          body: JSON.stringify({
            current_password: formData.currentPassword,
            new_password: formData.newPassword,
            new_password_confirmation: formData.confirmPassword,
          }),
        }
      );

      if (!response.ok) throw new Error("Şifre güncellenemedi");

      setFormData((prev) => ({
        ...prev,
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
      }));
      setSuccess("Şifre başarıyla değiştirildi!");
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(
        extractErrorMessage(err, "Bir hata oluştu")
      );
    } finally {
      setLoading(false);
    }
  };

  // Connect OAuth
  const handleConnectOAuth = async (providerId: string) => {
    const authUrl = `${process.env.NEXT_PUBLIC_API_URL}/oauth/${providerId}/authorize?redirect_uri=${encodeURIComponent(
      `${window.location.origin}/account/settings`
    )}`;
    window.location.assign(authUrl);
  };

  // Disconnect OAuth
  const handleDisconnectOAuth = async (providerId: string) => {
    if (!token) return;

    setLoading(true);
    try {
      const response = await fetch(
        `${process.env.NEXT_PUBLIC_API_URL}/v1/oauth/${providerId}/disconnect`,
        {
          method: "POST",
          headers: {
            Authorization: `Bearer ${token}`,
          },
        }
      );

      if (!response.ok) throw new Error("Bağlantı kesilemedi");

      setOauthProviders((prev) =>
        prev.map((p) =>
          p.id === providerId ? { ...p, connected: false } : p
        )
      );
      setSuccess(`${providerId} bağlantısı kaldırıldı`);
      setTimeout(() => setSuccess(null), 3000);
    } catch (err) {
      setError(
        extractErrorMessage(err, "Bir hata oluştu")
      );
    } finally {
      setLoading(false);
    }
  };

  if (!isAuthenticated) return null;

  return (
    <AppLayout>
      {/* Header */}
      <section className="account-page-head">
        <div>
          <p className="eyebrow">Hesabım</p>
          <h1>Ayarlar</h1>
        </div>
      </section>

      {/* Alert Messages */}
      {error && (
        <div className="flex gap-3 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
          <AlertCircle size={18} className="flex-shrink-0 mt-0.5" />
          <span>{error}</span>
        </div>
      )}
      {success && (
        <div className="flex gap-3 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">
          <CheckCircle size={18} className="flex-shrink-0 mt-0.5" />
          <span>{success}</span>
        </div>
      )}

      {/* Tabs */}
      <div className="flex gap-2 border-b border-gray-200 overflow-x-auto mb-8">
        <button
          onClick={() => setActiveTab("profile")}
          className={`px-4 py-3 font-semibold border-b-2 transition-colors whitespace-nowrap ${
            activeTab === "profile"
              ? "border-orange-500 text-orange-600"
              : "border-transparent text-gray-600 hover:text-gray-900"
          }`}
        >
          Profil Bilgileri
        </button>
        <button
          onClick={() => setActiveTab("security")}
          className={`px-4 py-3 font-semibold border-b-2 transition-colors whitespace-nowrap ${
            activeTab === "security"
              ? "border-orange-500 text-orange-600"
              : "border-transparent text-gray-600 hover:text-gray-900"
          }`}
        >
          Güvenlik
        </button>
        <button
          onClick={() => setActiveTab("oauth")}
          className={`px-4 py-3 font-semibold border-b-2 transition-colors whitespace-nowrap ${
            activeTab === "oauth"
              ? "border-orange-500 text-orange-600"
              : "border-transparent text-gray-600 hover:text-gray-900"
          }`}
        >
          Bağlantılar
        </button>
      </div>

      {/* Profile Tab */}
      {activeTab === "profile" && (
        <form onSubmit={handleUpdateProfile} className="max-w-2xl space-y-6">
          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              Ad Soyad
            </label>
            <input
              type="text"
              name="name"
              value={formData.name}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="Adınızı girin"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              E-posta
            </label>
            <input
              type="email"
              name="email"
              value={formData.email}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="E-posta adresinizi girin"
            />
          </div>

          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              Telefon
            </label>
            <input
              type="tel"
              name="phone"
              value={formData.phone}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="+90 5XX XXX XXXX"
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full md:w-auto px-6 py-2.5 bg-orange-600 text-white font-semibold rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            {loading && <Loader2 size={18} className="animate-spin" />}
            Güncelle
          </button>
        </form>
      )}

      {/* Security Tab */}
      {activeTab === "security" && (
        <form onSubmit={handleUpdatePassword} className="max-w-2xl space-y-6">
          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              Mevcut Şifre
            </label>
            <input
              type="password"
              name="currentPassword"
              value={formData.currentPassword}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="Mevcut şifrenizi girin"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              Yeni Şifre
            </label>
            <input
              type="password"
              name="newPassword"
              value={formData.newPassword}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="Yeni şifrenizi girin (min. 8 karakter)"
              required
            />
          </div>

          <div>
            <label className="block text-sm font-semibold mb-2 text-gray-900">
              Şifreyi Onayla
            </label>
            <input
              type="password"
              name="confirmPassword"
              value={formData.confirmPassword}
              onChange={handleInputChange}
              className="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500"
              placeholder="Şifrenizi tekrar girin"
              required
            />
          </div>

          <button
            type="submit"
            disabled={loading}
            className="w-full md:w-auto px-6 py-2.5 bg-orange-600 text-white font-semibold rounded-lg hover:bg-orange-700 disabled:opacity-50 flex items-center justify-center gap-2"
          >
            {loading && <Loader2 size={18} className="animate-spin" />}
            Şifreyi Değiştir
          </button>
        </form>
      )}

      {/* OAuth Tab */}
      {activeTab === "oauth" && (
        <div className="max-w-2xl space-y-4">
          <p className="text-gray-600 mb-6">
            Bağlı hesaplar
          </p>

          {oauthProviders.map((provider) => (
            <div
              key={provider.id}
              className="flex items-center justify-between p-4 border border-gray-200 rounded-lg"
            >
              <div className="flex items-center gap-3">
                <span className="grid h-9 w-9 place-items-center rounded-lg border border-gray-200 text-gray-700"><Link2 size={16} /></span>
                <div>
                  <h3 className="font-semibold text-gray-900">
                    {provider.name}
                  </h3>
                  <p className="text-sm text-gray-600">
                    {provider.connected
                      ? "Bağlı"
                      : "Bağlı değil"}
                  </p>
                </div>
              </div>

              {provider.connected ? (
                <button
                  onClick={() => handleDisconnectOAuth(provider.id)}
                  disabled={loading}
                  className="px-4 py-2 text-red-600 border border-red-300 rounded-lg hover:bg-red-50 disabled:opacity-50 flex items-center gap-2"
                >
                  <LogOut size={16} />
                  Bağlantıyı Kes
                </button>
              ) : (
                <button
                  onClick={() => handleConnectOAuth(provider.id)}
                  disabled={loading}
                  className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                >
                  Bağlan
                </button>
              )}
            </div>
          ))}
        </div>
      )}
    </AppLayout>
  );
}
