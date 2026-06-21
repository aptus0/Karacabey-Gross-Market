type CargoTrackingBoxProps = {
  code: string;
  status: string;
};

export function CargoTrackingBox({ code, status }: CargoTrackingBoxProps) {
  return (
    <section className="tracking-box" aria-label="Kargo takip">
      <span>{code}</span>
      <strong>{status}</strong>
    </section>
  );
}
