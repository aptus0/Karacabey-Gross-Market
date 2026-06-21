const keyPattern = /^[a-zA-Z0-9-]{8,128}\.txt$/;

type IndexNowKeyRouteProps = {
  params: Promise<{ key: string }>;
};

export async function GET(_: Request, { params }: IndexNowKeyRouteProps) {
  const { key: keyFile } = await params;
  const configuredKey = process.env.INDEXNOW_KEY;
  const expectedFile = configuredKey ? `${configuredKey}.txt` : "";

  if (!configuredKey || !keyPattern.test(keyFile) || keyFile !== expectedFile) {
    return new Response("Not found", { status: 404 });
  }

  return new Response(configuredKey, {
    headers: {
      "Cache-Control": "public, max-age=86400, s-maxage=86400",
      "Content-Type": "text/plain; charset=utf-8",
    },
  });
}
